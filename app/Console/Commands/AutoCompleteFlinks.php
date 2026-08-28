<?php

namespace App\Console\Commands;

use App\Domain\Flink\Actions\CompleteFlinkAction;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Enums\MatchStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rede de segurança da regra de dupla confirmação (ver
 * App\Domain\Match\Actions\ConfirmCompletionAction): se só um lado (empresa ou
 * profissional) confirmou a conclusão do Flink e o outro nunca agiu, este comando
 * completa automaticamente depois de `config('flinker.auto_complete_hours')`,
 * contado a partir da primeira confirmação — evita que o pagamento fique preso
 * indefinidamente por uma das partes simplesmente não confirmar.
 *
 * Só age quando pelo menos um lado já confirmou — um Flink em execução onde
 * ninguém confirmou nada não é auto-completado (não há indício de que o serviço
 * foi mesmo executado).
 *
 * Agendado em routes/console.php pra rodar periodicamente.
 */
class AutoCompleteFlinks extends Command
{
    protected $signature = 'flinks:auto-complete';

    protected $description = 'Completa automaticamente Flinks com só uma confirmação (empresa OU profissional) após o prazo configurado';

    public function handle(CompleteFlinkAction $completeFlinkAction): int
    {
        $deadline = now()->subHours((int) config('flinker.auto_complete_hours'));

        $candidates = Flink::query()
            ->where('status', FlinkStatus::InProgress)
            ->whereHas('matches', function ($query) use ($deadline) {
                $query->where('status', MatchStatus::Confirmed)
                    ->where(function ($q) use ($deadline) {
                        $q->where('professional_confirmed_at', '<=', $deadline)
                            ->orWhere('company_confirmed_at', '<=', $deadline);
                    });
            })
            ->get();

        $completed = 0;

        foreach ($candidates as $flink) {
            $match = $flink->matches()->where('status', MatchStatus::Confirmed)->first();

            if (! $match || $match->isFullyConfirmed()) {
                // Já foi completado no fluxo normal (ou não tem match confirmado) — nada a fazer.
                continue;
            }

            $earliestConfirmation = collect([$match->professional_confirmed_at, $match->company_confirmed_at])
                ->filter()
                ->min();

            if (! $earliestConfirmation || $earliestConfirmation->gt($deadline)) {
                continue;
            }

            $completeFlinkAction->handle($flink);
            $completed++;

            Log::info('Flink completado automaticamente por prazo (confirmação única expirada).', [
                'flink_id' => $flink->id,
                'match_id' => $match->id,
                'confirmado_por' => $match->professional_confirmed_at ? 'professional' : 'company',
                'confirmado_em' => $earliestConfirmation->toDateTimeString(),
            ]);
        }

        $this->info("Flinks completados automaticamente: {$completed}");

        return self::SUCCESS;
    }
}
