<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\MercadoPagoService;
use App\Domain\Wallet\Services\WalletService;

class DepositAction
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly MercadoPagoService $mercadoPago,
    ) {}

    /**
     * @return array{checkout_url: string}
     */
    public function handle(Wallet $wallet, float $amount): array
    {
        $externalReference = $this->mercadoPago->generateExternalReference();

        // Registra a transação como pendente — só vira 'completed' (e credita o saldo)
        // quando o webhook do Mercado Pago confirmar o pagamento (ver ProcessMercadoPagoWebhookAction).
        $this->walletService->createPending(
            $wallet,
            $amount,
            TransactionType::Deposit,
            $externalReference,
        );

        $preference = $this->mercadoPago->createDepositPreference($wallet, $amount, $externalReference);

        return ['checkout_url' => $preference['checkout_url']];
    }
}
