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
        // App 100% API (quem loga é o frontend React, não existe rota Blade "login").
        // Sem isso, o redirect padrão do Laravel pra usuário não-autenticado tenta
        // gerar route('login') quando a request não é reconhecida como "espera JSON"
        // (ex: acesso direto pela barra de endereço) e quebra com
        // RouteNotFoundException em vez de simplesmente devolver 401 — achado #11
        // da auditoria de 2026-10-01.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json(['message' => $e->getMessage() ?: 'Unauthenticated.'], 401);
        });
    })->create();
