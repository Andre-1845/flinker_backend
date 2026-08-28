<?php

namespace Tests\Feature\Flink;

use App\Domain\Company\Models\Company;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Professional\Models\Professional;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a regra de conclusão decidida na auditoria de 27/08/2026: dupla confirmação
 * (empresa + profissional), com conclusão automática por prazo se só um lado agir.
 */
class FlinkCompletionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeInProgressFlink(): array
    {
        $company = Company::factory()->create();
        $professional = Professional::factory()->create();

        Wallet::factory()->create(['user_id' => $company->user_id, 'balance' => 1000]);
        Wallet::factory()->create(['user_id' => $professional->user_id, 'balance' => 0]);

        $flink = Flink::factory()->create([
            'company_id' => $company->id,
            'status' => FlinkStatus::InProgress,
            'net_value' => 200,
            'platform_margin' => 14,
            'total_value' => 214,
        ]);

        $match = FlinkMatch::factory()->create([
            'flink_id' => $flink->id,
            'professional_id' => $professional->id,
            'status' => MatchStatus::Confirmed,
            'checked_in_at' => now(),
        ]);

        return compact('company', 'professional', 'flink', 'match');
    }

    public function test_split_only_happens_after_both_sides_confirm(): void
    {
        ['company' => $company, 'professional' => $professional, 'flink' => $flink] = $this->makeInProgressFlink();

        // Empresa confirma primeiro.
        $this->actingAs($company->user)
            ->putJson("/api/flinks/{$flink->id}/complete")
            ->assertOk();

        $this->assertSame(FlinkStatus::InProgress, $flink->fresh()->status);
        $this->assertSame('0.00', $professional->user->wallet->fresh()->balance);

        // Profissional confirma — agora sim o split acontece.
        $match = FlinkMatch::where('flink_id', $flink->id)->first();
        $this->actingAs($professional->user)
            ->putJson("/api/matches/{$match->id}/confirm-completion")
            ->assertOk();

        $flink->refresh();
        $this->assertSame(FlinkStatus::Completed, $flink->status);
        $this->assertSame('200.00', $professional->user->wallet->fresh()->balance);

        $this->assertDatabaseHas('transactions', [
            'flink_id' => $flink->id,
            'type' => TransactionType::PlatformFee->value,
            'wallet_id' => null,
            'amount' => 14,
        ]);
    }

    public function test_a_side_cannot_confirm_twice(): void
    {
        ['company' => $company, 'flink' => $flink, 'match' => $match] = $this->makeInProgressFlink();

        $this->actingAs($company->user)->putJson("/api/flinks/{$flink->id}/complete")->assertOk();

        $this->actingAs($company->user)
            ->putJson("/api/flinks/{$flink->id}/complete")
            ->assertStatus(422);
    }

    public function test_professional_cannot_confirm_before_checkin(): void
    {
        $company = Company::factory()->create();
        $professional = Professional::factory()->create();
        Wallet::factory()->create(['user_id' => $professional->user_id]);

        $flink = Flink::factory()->create([
            'company_id' => $company->id,
            'status' => FlinkStatus::Confirmed, // ainda sem check-in
        ]);

        $match = FlinkMatch::factory()->create([
            'flink_id' => $flink->id,
            'professional_id' => $professional->id,
            'status' => MatchStatus::Confirmed,
        ]);

        $this->actingAs($professional->user)
            ->putJson("/api/matches/{$match->id}/confirm-completion")
            ->assertStatus(422);
    }

    public function test_auto_complete_command_closes_flinks_past_the_deadline_with_only_one_confirmation(): void
    {
        config(['flinker.auto_complete_hours' => 48]);

        ['company' => $company, 'professional' => $professional, 'flink' => $flink, 'match' => $match] = $this->makeInProgressFlink();

        // Só o profissional confirmou, há 49h — passou do prazo de 48h.
        $match->update(['professional_confirmed_at' => now()->subHours(49)]);

        $this->artisan('flinks:auto-complete')->assertExitCode(0);

        $flink->refresh();
        $this->assertSame(FlinkStatus::Completed, $flink->status);
        $this->assertSame('200.00', $professional->user->wallet->fresh()->balance);
    }

    public function test_auto_complete_command_leaves_flinks_within_the_deadline_untouched(): void
    {
        config(['flinker.auto_complete_hours' => 48]);

        ['flink' => $flink, 'match' => $match] = $this->makeInProgressFlink();

        // Confirmou há só 2h — ainda dentro do prazo, não deve completar sozinho.
        $match->update(['professional_confirmed_at' => now()->subHours(2)]);

        $this->artisan('flinks:auto-complete')->assertExitCode(0);

        $this->assertSame(FlinkStatus::InProgress, $flink->fresh()->status);
    }

    public function test_auto_complete_command_ignores_flinks_with_no_confirmation_at_all(): void
    {
        config(['flinker.auto_complete_hours' => 48]);

        ['flink' => $flink] = $this->makeInProgressFlink();

        // Ninguém confirmou nada, mesmo que o check-in seja antigo — não auto-completa.
        $this->artisan('flinks:auto-complete')->assertExitCode(0);

        $this->assertSame(FlinkStatus::InProgress, $flink->fresh()->status);
    }
}
