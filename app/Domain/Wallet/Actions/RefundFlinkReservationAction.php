<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Flink\Models\Flink;
use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\WalletService;

class RefundFlinkReservationAction
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Se o Flink teve uma reserva de pagamento (empresa já tinha saldo garantido) e ainda
     * não foi concluído, devolve o valor pra carteira da empresa ao cancelar.
     */
    public function handle(Flink $flink): ?Transaction
    {
        $reservation = Transaction::query()
            ->where('flink_id', $flink->id)
            ->where('type', TransactionType::Reservation)
            ->where('status', TransactionStatus::Completed)
            ->first();

        if (! $reservation) {
            return null;
        }

        // Já foi estornado antes? Evita reembolso duplicado.
        $alreadyRefunded = Transaction::query()
            ->where('flink_id', $flink->id)
            ->where('type', TransactionType::Refund)
            ->exists();

        if ($alreadyRefunded) {
            return null;
        }

        return $this->walletService->credit(
            $reservation->wallet,
            (float) $reservation->amount,
            TransactionType::Refund,
            $flink,
        );
    }
}
