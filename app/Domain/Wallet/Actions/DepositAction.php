<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;

class DepositAction
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * @return array{checkout_url: string}
     */
    public function handle(Wallet $wallet, float $amount): array
    {
        $externalReference = $this->paymentGateway->generateExternalReference();

        // Registra a transação como pendente — só vira 'completed' (e credita o saldo)
        // quando o webhook do gateway confirmar o pagamento (ver ProcessPaymentWebhookAction).
        $this->walletService->createPending(
            $wallet,
            $amount,
            TransactionType::Deposit,
            $externalReference,
        );

        $preference = $this->paymentGateway->createDepositPreference($wallet, $amount, $externalReference);

        return ['checkout_url' => $preference['checkout_url']];
    }
}
