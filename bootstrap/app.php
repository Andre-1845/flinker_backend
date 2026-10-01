<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // App 100% API (quem loga é o frontend React, não existe rota Blade "login")
        // pro guard padrão. Sem isso, o redirect padrão do Laravel pra usuário
        // não-autenticado tenta gerar route('login') quando a request não é
        // reconhecida como "espera JSON" (ex: acesso direto pela barra de
        // endereço) e quebra com RouteNotFoundException em vez de simplesmente
        // devolver 401 — achado #11 da auditoria de 2026-10-01.
        //
        // Importante: isso só pode valer pras rotas /api/*. O painel Filament
        // (/admin) usa o guard 'admin' com seu próprio redirect pra
        // /admin/login (via Authenticate::redirectUsing, registrado pelo
        // Filament) — forçar JSON também ali quebra esse redirect (pego pelo
        // teste Tests\Feature\Admin\AdminPanelAccessTest::guest_is_redirected_to_login
        // ao rodar o PHPUnit depois desta mudança). Retornar void nos outros
        // casos deixa o comportamento padrão do Laravel/Filament decidir.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage() ?: 'Unauthenticated.'], 401);
            }
        });
    })->create();
