<?php

namespace App\Console\Commands;

use App\Domain\Match\Models\Message;
use App\Domain\Settings\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Minimização de dados do chat (achado #6, pedido do Andre 2026-10-02): apaga
 * as mensagens de matches cujo Flink foi concluído há mais de
 * `chat_retention_days` (configurável via Filament → Configurações). 0 =
 * nunca apaga.
 *
 * Âncora é `flinks.completed_at`, não os `*_confirmed_at` do match — esses
 * dois campos podem nunca ficar completos quando o Flink é concluído pelo
 * comando `flinks:auto-complete` (só um lado confirmou, o outro nunca agiu),
 * então não são uma base confiável pra saber "quando os dois lados
 * concluíram" (ver CompleteFlinkAction, que é o único lugar que preenche
 * completed_at, por qualquer um dos dois caminhos de conclusão).
 *
 * Só as mensagens são apagadas — o match, o Flink e as transações continuam
 * intactos (rastro financeiro/auditoria preservado; só o conteúdo da
 * conversa, o dado pessoal sensível, é removido).
 *
 * Agendado em routes/console.php pra rodar 1x por dia (a janela é de dias,
 * não precisa da granularidade do auto-complete de pagamento).
 */
class PruneExpiredChats extends Command
{
    protected $signature = 'chat:prune-expired';

    protected $description = 'Apaga mensagens de matches concluídos há mais de chat_retention_days (0 = nunca apaga)';

    public function handle(SettingsService $settings): int
    {
        $days = (int) $settings->getFloat('chat_retention_days', 60);

        if ($days <= 0) {
            $this->info('Retenção configurada como 0 (nunca apagar) — nada a fazer.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $deleted = Message::whereHas('match.flink', function ($query) use ($cutoff) {
            $query->whereNotNull('completed_at')->where('completed_at', '<=', $cutoff);
        })->delete();

        if ($deleted > 0) {
            Log::info('Mensagens de chat apagadas por expiração de retenção.', [
                'quantidade' => $deleted,
                'retention_days' => $days,
                'cutoff' => $cutoff->toDateTimeString(),
            ]);
        }

        $this->info("Mensagens apagadas: {$deleted}");

        return self::SUCCESS;
    }
}
