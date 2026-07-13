<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integração com o Mercado Pago via API REST direta (sem o SDK oficial — evita uma
 * dependência a mais e fica mais fácil de auditar/testar).
 *
 * ⚠️ IMPORTANTE: esta classe ainda não foi testada contra credenciais reais do Mercado
 * Pago (sandbox ou produção). Antes de usar em produção:
 * 1. Criar uma conta de teste no Mercado Pago Developers e gerar credenciais de sandbox.
 * 2. Configurar MERCADOPAGO_ACCESS_TOKEN no .env.
 * 3. Testar o fluxo de depósito de ponta a ponta (criar preferência → pagar no sandbox →
 *    confirmar que o webhook chega e credita a carteira).
 * 4. Configurar a URL de notificação (`notification_url`) para um endereço publicamente
 *    acessível (não funciona com `localhost` — usar ngrok ou similar em dev).
 */
class MercadoPagoService
{
    private string $baseUrl = 'https://api.mercadopago.com';

    private function accessToken(): string
    {
        $token = config('services.mercadopago.access_token');

        if (! $token) {
            throw new RuntimeException(
                'MERCADOPAGO_ACCESS_TOKEN não configurado. Veja o .env.example.'
            );
        }

        return $token;
    }

    /**
     * Cria uma preferência de pagamento (checkout) para a empresa depositar na carteira.
     *
     * @return array{checkout_url: string, preference_id: string}
     */
    public function createDepositPreference(Wallet $wallet, float $amount, string $externalReference): array
    {
        $response = Http::withToken($this->accessToken())
            ->post("{$this->baseUrl}/checkout/preferences", [
                'items' => [[
                    'title' => 'Depósito na carteira Flinker',
                    'quantity' => 1,
                    'currency_id' => 'BRL',
                    'unit_price' => round($amount, 2),
                ]],
                'external_reference' => $externalReference,
                'notification_url' => config('services.mercadopago.webhook_url'),
                'back_urls' => [
                    'success' => config('services.mercadopago.return_url'),
                    'failure' => config('services.mercadopago.return_url'),
                    'pending' => config('services.mercadopago.return_url'),
                ],
            ]);

        if ($response->failed()) {
            Log::error('Mercado Pago: falha ao criar preferência de depósito', [
                'wallet_id' => $wallet->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Não foi possível iniciar o depósito no Mercado Pago.');
        }

        $data = $response->json();

        return [
            'checkout_url' => $data['init_point'],
            'preference_id' => $data['id'],
        ];
    }

    /**
     * Busca um pagamento direto na API do Mercado Pago pra confirmar o status —
     * nunca confiar só no corpo do webhook, sempre reconsultar a fonte.
     */
    public function fetchPayment(string $paymentId): array
    {
        $response = Http::withToken($this->accessToken())
            ->get("{$this->baseUrl}/v1/payments/{$paymentId}");

        if ($response->failed()) {
            throw new RuntimeException("Não foi possível consultar o pagamento {$paymentId} no Mercado Pago.");
        }

        return $response->json();
    }

    /**
     * Gera um identificador único pra correlacionar uma transação nossa com o
     * external_reference enviado ao Mercado Pago.
     */
    public function generateExternalReference(): string
    {
        return 'flinker_'.Str::uuid();
    }
}
