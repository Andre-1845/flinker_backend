<?php

namespace Tests\Feature\Admin;

use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antes desta auditoria não existia NENHUMA forma de aprovar um saque de verdade
 * (achado crítico) — este teste garante que o botão do painel Filament realmente
 * completa/reverte a transação via WalletService, não só troca uma cor na tela.
 */
class TransactionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_admin_can_approve_a_pending_withdrawal(): void
    {
        $this->actingAsAdmin();

        $wallet = Wallet::factory()->create(['balance' => 50]);
        $transaction = Transaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Withdrawal,
            'status' => TransactionStatus::Pending,
            'amount' => 80,
        ]);

        Livewire::test(ListTransactions::class)
            ->callTableAction('approve_withdrawal', $transaction);

        $this->assertSame(TransactionStatus::Completed, $transaction->fresh()->status);
        // Aprovar não mexe no saldo de novo — já foi debitado na hora do pedido de saque.
        $this->assertSame('50.00', $wallet->fresh()->balance);
    }

    public function test_admin_can_reject_a_pending_withdrawal_and_the_balance_is_refunded(): void
    {
        $this->actingAsAdmin();

        $wallet = Wallet::factory()->create(['balance' => 50]);
        $transaction = Transaction::factory()->create([
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Withdrawal,
            'status' => TransactionStatus::Pending,
            'amount' => 80,
        ]);

        Livewire::test(ListTransactions::class)
            ->callTableAction('reject_withdrawal', $transaction);

        $this->assertSame(TransactionStatus::Failed, $transaction->fresh()->status);
        $this->assertSame('130.00', $wallet->fresh()->balance);
    }
}
