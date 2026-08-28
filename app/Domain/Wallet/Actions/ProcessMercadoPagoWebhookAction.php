<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessMercadoPagoWebhookAction
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Processa a notificação de webhook completa: valida a assinatura, extrai o
     * `payment_id` e reconsulta o gateway pra confirmar o status de verdade antes de
     * mexer em qualquer saldo.
     */
    public function handle(Request $request): void
    {
        if (! $this->paymentGateway->verifyWebhookSignature($request)) {
            Log::warning('Webhook de pagamento rejeitado: assinatura inválida ou ausente.', [
                'driver' => $this->paymentGateway->driverName(),
                'ip' => $request->ip(),
            ]);

            return;
        }

        $paymentId = $request->input('data.id') ?? $request->query('id');

        if (! $paymentId) {
            return;
        }

        $this->processPayment((string) $paymentId);
    }

    /**
     * @param  string  $paymentId  ID do pagamento enviado pelo gateway na notificação
     */
    private function processPayment(string $paymentId): void
    {
        // Nunca confiamos só no corpo do webhook — reconsultamos a API pra confirmar
        // o status e o valor de verdade.
        $payment = $this->paymentGateway->fetchPayment($paymentId);

        $externalReference = $payment['external_reference'] ?? null;

        if (! $externalReference) {
            Log::warning('Webhook de pagamento sem external_reference', ['payment_id' => $paymentId]);

            return;
        }

        $transaction = Transaction::query()
            ->where('external_reference', $externalReference)
            ->where('status', TransactionStatus::Pending)
            ->first();

        if (! $transaction) {
            Log::info('Webhook de pagamento: transação não encontrada ou já processada', [
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
