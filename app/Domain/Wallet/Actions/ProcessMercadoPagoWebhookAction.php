<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\MercadoPagoService;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\Log;

class ProcessMercadoPagoWebhookAction
{
    public function __construct(
        private readonly MercadoPagoService $mercadoPago,
        private readonly WalletService $walletService,
    ) {}

    /**
     * @param  string  $paymentId  ID do pagamento enviado pelo Mercado Pago na notificação
     */
    public function handle(string $paymentId): void
    {
        // Nunca confiamos só no corpo do webhook — reconsultamos a API pra confirmar
        // o status e o valor de verdade.
        $payment = $this->mercadoPago->fetchPayment($paymentId);

        $externalReference = $payment['external_reference'] ?? null;

        if (! $externalReference) {
            Log::warning('Webhook Mercado Pago sem external_reference', ['payment_id' => $paymentId]);
            return;
        }

        $transaction = Transaction::query()
            ->where('external_reference', $externalReference)
            ->where('status', TransactionStatus::Pending)
            ->first();

        if (! $transaction) {
            Log::info('Webhook Mercado Pago: transação não encontrada ou já processada', [
                'payment_id' => $paymentId,
                'external_reference' => $externalReference,
            ]);
            return;
        }

        match ($payment['status'] ?? null) {
            'approved' => $this->walletService->markCompleted($transaction),
            'rejected', 'cancelled' => $this->walletService->markFailed($transaction),
            default => null, // in_process, pending, etc. — aguarda próxima notificação
        };
    }
}
