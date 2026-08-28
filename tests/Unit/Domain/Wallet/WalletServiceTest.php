<?php

namespace Tests\Unit\Domain\Wallet;

use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WalletService;
    }

    public function test_credit_increases_balance_and_creates_completed_transaction(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $transaction = $this->service->credit($wallet, 50, TransactionType::Earning);

        $this->assertSame('150.00', $wallet->fresh()->balance);
        $this->assertSame(TransactionStatus::Completed, $transaction->status);
        $this->assertSame(TransactionType::Earning, $transaction->type);
    }

    public function test_debit_decreases_balance_when_sufficient(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $this->service->debit($wallet, 40, TransactionType::Reservation);

        $this->assertSame('60.00', $wallet->fresh()->balance);
    }

    public function test_debit_throws_and_leaves_balance_untouched_when_insufficient(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 30]);

        try {
            $this->service->debit($wallet, 40, TransactionType::Reservation);
            $this->fail('Esperava ValidationException por saldo insuficiente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('balance', $e->errors());
        }

        $this->assertSame('30.00', $wallet->fresh()->balance);
        $this->assertSame(0, $wallet->fresh()->transactions()->count());
    }

    public function test_deposit_lifecycle_stays_pending_until_marked_completed(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);

        $transaction = $this->service->createPending($wallet, 100, TransactionType::Deposit, 'ext-ref-1');

        // Depósito pendente não pode creditar o saldo antes da confirmação do gateway —
        // é exatamente o que protege contra creditar duas vezes o mesmo depósito.
        $this->assertSame('0.00', $wallet->fresh()->balance);
        $this->assertSame(TransactionStatus::Pending, $transaction->status);

        $this->service->markCompleted($transaction);

        $this->assertSame('100.00', $wallet->fresh()->balance);
        $this->assertSame(TransactionStatus::Completed, $transaction->fresh()->status);
    }

    public function test_withdraw_debits_immediately_but_refunds_on_failure(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 200]);

        $transaction = $this->service->debitPending($wallet, 80, TransactionType::Withdrawal);

        // Debita na hora (evita saque duplicado do mesmo saldo) mesmo estando 'pending'.
        $this->assertSame('120.00', $wallet->fresh()->balance);

        $this->service->markFailed($transaction);

        // Falhou (ex: Pix não confirmado) — o valor volta pra carteira.
        $this->assertSame('200.00', $wallet->fresh()->balance);
        $this->assertSame(TransactionStatus::Failed, $transaction->fresh()->status);
    }

    public function test_concurrent_debits_never_take_balance_negative(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        // Duas tentativas de debitar 80 cada, sequenciais mas simulando corrida:
        // a segunda precisa falhar porque o lock pessimista já viu o saldo atualizado.
        $this->service->debit($wallet, 80, TransactionType::Reservation);

        $this->expectException(ValidationException::class);
        $this->service->debit($wallet, 80, TransactionType::Reservation);
    }
}
