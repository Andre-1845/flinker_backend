<?php

namespace Tests\Feature\Wallet;

use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // MercadoPagoService::fetchPayment() exige um access token configurado antes
        // de sequer tentar a chamada HTTP (que o Http::fake() abaixo intercepta).
        config(['services.mercadopago.access_token' => 'test-access-token']);
    }

    private function fakeApprovedPayment(string $paymentId, string $externalReference): void
    {
        Http::fake([
            "https://api.mercadopago.com/v1/payments/{$paymentId}" => Http::response([
                'id' => $paymentId,
                'status' => 'approved',
                'external_reference' => $externalReference,
            ], 200),
        ]);
    }

    private function signatureHeader(string $secret, string $dataId, string $requestId, string $ts): string
    {
        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', strtolower($dataId), $requestId, $ts);
        $v1 = hash_hmac('sha256', $manifest, $secret);

        return "ts={$ts},v1={$v1}";
    }

    public function test_valid_signature_processes_the_deposit(): void
    {
        config(['services.mercadopago.webhook_secret' => 'topsecret']);

        $wallet = Wallet::factory()->create(['balance' => 0]);
        $transaction = Transaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Deposit,
            'status' => TransactionStatus::Pending,
            'amount' => 100,
            'external_reference' => 'flinker_abc123',
        ]);

        $this->fakeApprovedPayment('999', 'flinker_abc123');

        $ts = (string) time();
        $signature = $this->signatureHeader('topsecret', '999', 'req-1', $ts);

        $this->withHeaders([
            'x-signature' => $signature,
            'x-request-id' => 'req-1',
        ])->postJson('/api/webhooks/mercadopago?id=999')->assertOk();

        $this->assertSame(TransactionStatus::Completed, $transaction->fresh()->status);
        $this->assertSame('100.00', $wallet->fresh()->balance);
    }

    public function test_invalid_signature_is_rejected_and_nothing_is_credited(): void
    {
        config(['services.mercadopago.webhook_secret' => 'topsecret']);

        $wallet = Wallet::factory()->create(['balance' => 0]);
        $transaction = Transaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Deposit,
            'status' => TransactionStatus::Pending,
            'amount' => 100,
            'external_reference' => 'flinker_abc123',
        ]);

        $this->fakeApprovedPayment('999', 'flinker_abc123');

        $this->withHeaders([
            'x-signature' => 'ts=123,v1=assinatura-forjada',
            'x-request-id' => 'req-1',
        ])->postJson('/api/webhooks/mercadopago?id=999')
            // A rota sempre responde 200 (pra não fazer o Mercado Pago reenviar em loop),
            // mas nada deve ter sido processado.
            ->assertOk();

        $this->assertSame(TransactionStatus::Pending, $transaction->fresh()->status);
        $this->assertSame('0.00', $wallet->fresh()->balance);
    }

    public function test_missing_secret_falls_back_to_permissive_but_still_requires_reconfirmation_from_the_api(): void
    {
        config(['services.mercadopago.webhook_secret' => null]);

        $wallet = Wallet::factory()->create(['balance' => 0]);
        $transaction = Transaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Deposit,
            'status' => TransactionStatus::Pending,
            'amount' => 100,
            'external_reference' => 'flinker_abc123',
        ]);

        $this->fakeApprovedPayment('999', 'flinker_abc123');

        // Sem segredo configurado (cenário local/dev) — nenhum header de assinatura,
        // mas o processamento segue porque sempre reconsulta a API do gateway.
        $this->postJson('/api/webhooks/mercadopago?id=999')->assertOk();

        $this->assertSame(TransactionStatus::Completed, $transaction->fresh()->status);
    }
}
