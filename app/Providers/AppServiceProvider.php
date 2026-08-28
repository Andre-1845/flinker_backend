<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A API não tem tela de reset — quem renderiza o formulário é o frontend
        // (flinker_app). O e-mail de redefinição de senha precisa apontar pra lá, não
        // pra uma rota do backend.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $frontendUrl = rtrim(config('app.frontend_url'), '/');

            return sprintf(
                '%s/reset-password?token=%s&email=%s',
                $frontendUrl,
                $token,
                urlencode($notifiable->getEmailForPasswordReset())
            );
        });

        // Os models de domínio vivem em App\Domain\{Modulo}\Models\{Model}, não em
        // App\Models — o resolvedor padrão do Laravel pra Model::factory() só sabe
        // mapear a partir de App\Models\*, então sem isso toda factory de model de
        // domínio quebra com "Factory not found" (bug pré-existente, nunca detectado
        // porque não havia testes usando essas factories). Todas as factories deste
        // projeto seguem o padrão plano `Database\Factories\{Model}Factory`.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }
}
