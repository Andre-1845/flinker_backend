<?php

namespace App\Domain\Match\Actions;

use App\Domain\Flink\Actions\CompleteFlinkAction;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regra de conclusão do Flink (decisão registrada na auditoria de 27/08/2026):
 * dupla confirmação. A empresa confirma pelo lado dela (`PUT /flinks/{id}/complete`,
 * ver FlinkController::complete) e o profissional pelo dele
 * (`PUT /matches/{id}/confirm-completion`, ver MatchController::confirmCompletion) —
 * o split de pagamento (CompleteFlinkAction) só roda quando os dois já confirmaram.
 *
 * Se só um lado confirmar e o outro nunca agir, o comando agendado
 * `flinks:auto-complete` fecha automaticamente depois de
 * `config('flinker.auto_complete_hours')` — protege o profissional de ficar sem
 * receber por uma empresa que simplesmente não confirma.
 */
class ConfirmCompletionAction
{
    public function __construct(
        private readonly CompleteFlinkAction $completeFlinkAction,
    ) {}

    public function handle(FlinkMatch $match, string $confirmedBy): FlinkMatch
    {
        if (! in_array($confirmedBy, ['professional', 'company'], true)) {
            throw new \InvalidArgumentException("confirmedBy inválido: {$confirmedBy}");
        }

        if ($match->status !== MatchStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => 'Só é possível confirmar a conclusão de um match já confirmado.',
            ]);
        }

        $flink = $match->flink;

        if ($flink->status !== FlinkStatus::InProgress) {
            throw ValidationException::withMessages([
                'status' => 'A conclusão só pode ser confirmada depois do check-in (Flink em execução).',
            ]);
        }

        $column = $confirmedBy === 'professional' ? 'professional_confirmed_at' : 'company_confirmed_at';

        if ($match->{$column} !== null) {
            throw ValidationException::withMessages([
                'status' => 'Você já confirmou a conclusão deste Flink.',
            ]);
        }

        return DB::transaction(function () use ($match, $flink, $column) {
            $match->update([$column => now()]);
            $match->refresh();

            if ($match->isFullyConfirmed()) {
                $this->completeFlinkAction->handle($flink);
            }

            return $match->fresh(['flink', 'professional']);
        });
    }
}
