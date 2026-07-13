<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;

class WithdrawAction
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Debita o valor imediatamente (evita que o mesmo saldo seja sacado duas vezes),
     * mas a transação fica 'pending' até a confirmação de que o Pix foi enviado.
     *
     * ⚠️ Ainda não integrado com a API de pagamentos (payout) do Mercado Pago — isso
     * exige aprovação de conta business lá. Por enquanto, a confirmação é manual (ver
     * Fase 6 — Admin, endpoint de aprovação de saques).
     */
    public function handle(Wallet $wallet, float $amount): Transaction
    {
        return $this->walletService->debitPending(
            $wallet,
            $amount,
            TransactionType::Withdrawal,
        );
    }
}
