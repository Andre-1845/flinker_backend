<?php

namespace App\Http\Controllers\Api;

use App\Domain\Company\Actions\RegisterCompanyAction;
use App\Domain\Professional\Actions\RegisterProfessionalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\RegisterProfessionalRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    public function registerProfessional(RegisterProfessionalRequest $request, RegisterProfessionalAction $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        $this->sendVerificationEmail($user);

        return $this->respondWithToken($user, 201);
    }

    public function registerCompany(RegisterCompanyRequest $request, RegisterCompanyAction $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        $this->sendVerificationEmail($user);

        return $this->respondWithToken($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Esta conta está desativada.',
            ], 403);
        }

        return $this->respondWithToken($user->load(['professional', 'company']), 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    /**
     * Dispara o e-mail de redefinição de senha (via `password.reset.url`, ver
     * AppServiceProvider — o link aponta pro frontend, não pro backend).
     *
     * Sempre responde com a mesma mensagem genérica, exista ou não o e-mail — evita
     * que alguém use esse endpoint pra descobrir se um e-mail está cadastrado.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            Log::warning('Falha ao enviar link de redefinição de senha.', [
                'status' => $status,
            ]);
        }

        return response()->json([
            'message' => 'Se esse e-mail estiver cadastrado, enviamos um link de redefinição de senha.',
        ]);
    }

    /**
     * Confirma a redefinição de senha a partir do token recebido por e-mail.
     * Revoga todos os tokens de API existentes do usuário (ex: sessões antigas em
     * outros dispositivos) — quem redefiniu a senha precisa logar de novo.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // o cast 'hashed' do model já faz o hash
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Não foi possível redefinir a senha. O link pode ter expirado — solicite um novo.',
            ], 422);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso. Faça login novamente.',
        ]);
    }

    /**
     * Reenvia o e-mail de confirmação pro usuário logado (ex: o primeiro não chegou
     * ou o link de 60 minutos expirou).
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Este e-mail já foi confirmado.']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::error('Falha ao reenviar e-mail de confirmação de conta.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Não foi possível enviar o e-mail agora. Tente novamente em instantes.',
            ], 503);
        }

        return response()->json(['message' => 'Enviamos um novo link de confirmação para o seu e-mail.']);
    }

    /**
     * Dispara o evento `Registered` — o Laravel já escuta ele e envia o e-mail de
     * confirmação (User implementa MustVerifyEmail).
     *
     * Chamado depois que a transação do cadastro já fechou: se o SMTP falhar aqui,
     * a conta continua criada e o cadastro responde 201 normalmente — o usuário
     * pede o reenvio depois em POST /auth/email/verification-notification.
     */
    private function sendVerificationEmail(User $user): void
    {
        try {
            event(new Registered($user));
        } catch (Throwable $e) {
            Log::error('Falha ao enviar e-mail de confirmação de conta.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function respondWithToken(User $user, int $status): JsonResponse
    {
        $token = $user->createToken('flinker-api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], $status);
    }
}
