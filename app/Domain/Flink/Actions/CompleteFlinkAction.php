<?php

namespace App\Domain\Flink\Actions;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteFlinkAction
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Confirma a execução do Flink e faz o split do pagamento: o profissional recebe o
     * valor líquido (Earning) na carteira dele, e a margem da plataforma fica registrada
     * (platform_fee) sem dono — é receita da plataforma, não de um usuário.
     */
    public function handle(Flink $flink): Flink
    {
        if ($flink->status !== FlinkStatus::InProgress && $flink->status !== FlinkStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => 'Este Flink só pode ser concluído depois de confirmado (e idealmente já com check-in feito).',
            ]);
        }

        $match = FlinkMatch::query()
            ->where('flink_id', $flink->id)
            ->where('status', MatchStatus::Confirmed)
            ->first();

        if (! $match) {
            throw ValidationException::withMessages([
                'match' => 'Não há um profissional confirmado para este Flink.',
            ]);
        }

        return DB::transaction(function () use ($flink, $match) {
            $professionalWallet = $match->professional->user->wallet;

            $this->walletService->credit(
                $professionalWallet,
                (float) $flink->net_value,
                TransactionType::Earning,
                $flink,
            );

            Transaction::create([
                'wallet_id' => null,
                'flink_id' => $flink->id,
                'type' => TransactionType::PlatformFee,
                'amount' => $flink->platform_margin,
                'status' => TransactionStatus::Completed,
            ]);

            $flink->update(['status' => FlinkStatus::Completed]);

            return $flink->fresh();
        });
    }
}
