<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function professionalPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Teste',
            'email' => 'maria@example.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
            'cpf' => '12345678901',
            'phone' => '21999990000',
        ], $overrides);
    }

    private function verificationUrl(User $user, ?string $email = null, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->getKey(),
            'hash' => sha1($email ?? $user->email),
        ]);
    }

    public function test_professional_registration_sends_the_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register/professional', $this->professionalPayload());

        $response->assertCreated()->assertJsonPath('user.email_verified_at', null);

        $user = User::where('email', 'maria@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_company_registration_sends_the_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register/company', [
            'name' => 'Empresa Teste',
            'email' => 'empresa@example.com',
            'password' => 'senha-segura-123',
            'password_confirmation' => 'senha-segura-123',
            'cnpj' => '12345678000199',
            'responsible_name' => 'João Responsável',
            'responsible_cpf' => '98765432100',
            'phone' => '21999990000',
        ])->assertCreated();

        $user = User::where('email', 'empresa@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_still_succeeds_when_the_mail_server_fails(): void
    {
        Mail::shouldReceive('mailer')->andThrow(new RuntimeException('SMTP fora do ar'));

        $this->postJson('/api/auth/register/professional', $this->professionalPayload())
            ->assertCreated()
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'maria@example.com']);
    }

    public function test_verification_email_is_in_portuguese_and_links_to_the_signed_route(): void
    {
        $user = User::factory()->unverified()->create();

        $mail = (new VerifyEmail)->toMail($user);
        $this->assertStringContainsString('/email/verify/'.$user->id.'/'.sha1($user->email), $mail->actionUrl);

        // O idioma vem de User::preferredLocale(), aplicado pelo Laravel no envio.
        $this->assertSame('pt_BR', $user->preferredLocale());
        app()->setLocale('pt_BR');
        $this->assertSame('Confirmar e-mail', (new VerifyEmail)->toMail($user)->subject);
        $this->assertSame('Redefinição de senha', (new ResetPassword('token'))->toMail($user)->subject);
    }

    public function test_valid_link_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))
            ->assertOk()
            ->assertSee('E-mail confirmado!');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_clicking_the_link_twice_is_harmless(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $this->get($url)->assertOk();
        $verifiedAt = $user->fresh()->email_verified_at;

        $this->get($url)->assertOk()->assertSee('E-mail confirmado!');
        $this->assertEquals($verifiedAt, $user->fresh()->email_verified_at);
    }

    public function test_tampered_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user).'x')
            ->assertForbidden()
            ->assertSee('Link inválido ou expirado');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $this->travel(61)->minutes();

        $this->get($url)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_link_for_a_different_email_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user, email: 'outro@example.com'))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_can_request_a_new_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_does_nothing_for_an_already_verified_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Este e-mail já foi confirmado.');

        Notification::assertNothingSent();
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/auth/email/verification-notification')->assertUnauthorized();
    }
}
