<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Destino do link do e-mail de confirmação de conta.
 *
 * Responde com uma página HTML simples (não JSON): quem chega aqui clicou num
 * link dentro do cliente de e-mail, sem token Sanctum e fora do frontend. A
 * página mostra o resultado e um botão que leva pro Flinker (FRONTEND_URL).
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): Response
    {
        $user = User::find($id);

        // A assinatura cobre a URL inteira (id, hash e validade); o hash amarra o
        // link ao e-mail atual da conta — se o e-mail mudou, o link antigo morre.
        $valid = $request->hasValidSignature()
            && $user
            && hash_equals(sha1($user->getEmailForVerification()), $hash);

        if (! $valid) {
            return $this->page(verified: false, status: 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        return $this->page(verified: true, status: 200);
    }

    private function page(bool $verified, int $status): Response
    {
        return response()->view('auth.email-verified', [
            'verified' => $verified,
            'frontendUrl' => rtrim(config('app.frontend_url'), '/'),
        ], $status);
    }
}
