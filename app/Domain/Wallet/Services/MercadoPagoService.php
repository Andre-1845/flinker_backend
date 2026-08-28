<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integração com o Mercado Pago via API REST direta (sem o SDK oficial — evita uma
 * dependência a mais e fica mais fácil de auditar/testar).
 *
 * Implementa `PaymentGatewayInterface` — é uma escolha de gateway entre outras
 * possíveis, nunca deve ser referenciada diretamente fora deste arquivo e do
 * `PaymentGatewayServiceProvider` (ver esse provider pra trocar de gateway).
 *
 * ⚠️ IMPORTANTE: esta classe ainda não foi testada contra credenciais reais do Mercado
 * Pago (sandbox ou produção). Antes de usar em produção:
 * 1. Criar uma conta de teste no Mercado Pago Developers e gerar credenciais de sandbox.
 * 2. Configurar MERCADOPAGO_ACCESS_TOKEN no .env.
 * 3. Testar o fluxo de depósito de ponta a ponta (criar preferência → pagar no sandbox →
 *    confirmar que o webhook chega e credita a carteira).
 * 4. Configurar a URL de notificação (`notification_url`) para um endereço publicamente
 *    acessível (não funciona com `localhost` — usar ngrok ou similar em dev).
 * 5. Configurar MERCADOPAGO_WEBHOOK_SECRET (chave de assinatura da notificação
 *    webhook, disponível no painel do Mercado Pago) — sem isso, a assinatura da
 *    notificação não é validada (só a reconsulta da API, que já protege contra
 *    forjar o *conteúdo*, mas não contra chamadas não autênticas ao endpoint).
 */
class MercadoPagoService implements PaymentGatewayInterface
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

    public function driverName(): string
    {
        return 'mercadopago';
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

    /**
     * Valida o header `x-signature` que o Mercado Pago envia em toda notificação de
     * webhook, seguindo o algoritmo documentado por eles:
     *
     *   manifest = "id:{data.id};request-id:{x-request-id};ts:{ts};"
     *   assinatura_esperada = hash_hmac('sha256', manifest, MERCADOPAGO_WEBHOOK_SECRET)
     *
     * e compara com o valor `v1` do header `x-signature` (formato `ts=...,v1=...`).
     *
     * Isso é uma camada além de sempre reconsultar a API (`fetchPayment`) — garante
     * que a própria chamada ao endpoint veio do Mercado Pago, não só que o `payment_id`
     * informado existe.
     *
     * Retorna `true` (permissivo) e loga um aviso quando não há segredo configurado —
     * cenário esperado em ambiente local/dev. Em produção, configure
     * MERCADOPAGO_WEBHOOK_SECRET para que a verificação seja realmente aplicada.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');

        if (! $secret) {
            Log::warning('Mercado Pago: MERCADOPAGO_WEBHOOK_SECRET não configurado — assinatura do webhook não verificada.');

            return true;
        }

        $signatureHeader = $request->header('x-signature');
        $requestId = $request->header('x-request-id');
        $dataId = $request->query('data.id') ?? $request->query('id');

        if (! $signatureHeader || ! $requestId || ! $dataId) {
            Log::warning('Mercado Pago: notificação de webhook sem os headers/params esperados para validar assinatura.', [
                'has_signature' => (bool) $signatureHeader,
                'has_request_id' => (bool) $requestId,
                'has_data_id' => (bool) $dataId,
            ]);

            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, null);
            if ($key !== null && $value !== null) {
                $parts[trim($key)] = trim($value);
            }
        }

        $timestamp = $parts['ts'] ?? null;
        $expectedFromHeader = $parts['v1'] ?? null;

        if (! $timestamp || ! $expectedFromHeader) {
            Log::warning('Mercado Pago: header x-signature malformado.', ['x-signature' => $signatureHeader]);

            return false;
        }

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            strtolower((string) $dataId),
            $requestId,
            $timestamp,
        );

        $computed = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($computed, $expectedFromHeader);
    }
}
