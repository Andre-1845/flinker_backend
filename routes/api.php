<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FlinkController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Flinker
|--------------------------------------------------------------------------
|
| Rotas organizadas por módulo de domínio, conforme a especificação técnica.
| Cada fase do desenvolvimento vai preencher o grupo correspondente.
|
*/

// Fase 0 - healthcheck simples pra confirmar que a API está no ar
Route::get('/ping', fn () => response()->json(['status' => 'ok', 'service' => 'flinker-api']));

// Fase 1 - Autenticação (público)
Route::prefix('auth')->group(function () {
    Route::post('/register/professional', [AuthController::class, 'registerProfessional']);
    Route::post('/register/company', [AuthController::class, 'registerCompany']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Rotas autenticadas (todas as demais, protegidas por Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Fase 1 - Usuários
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me', [UserController::class, 'update']);

    // Fase 1 - Profissionais
    Route::apiResource('professionals', ProfessionalController::class)->only(['index', 'show', 'update']);

    // Fase 1 - Empresas
    Route::apiResource('companies', CompanyController::class)->only(['index', 'show', 'update']);

    // Fase 2 - Flinks
    Route::get('/flinks/active', [FlinkController::class, 'active']);
    Route::get('/flinks/company/{company}', [FlinkController::class, 'byCompany']);
    Route::apiResource('flinks', FlinkController::class);

    // Fase 3 - Matches
    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::put('/matches/{match}/accept', [MatchController::class, 'accept']);
    Route::put('/matches/{match}/confirm', [MatchController::class, 'confirm']);
    Route::post('/matches/{match}/checkin', [MatchController::class, 'checkin']);
    Route::put('/matches/{match}/cancel', [MatchController::class, 'cancel']);

    // Fase 3 - Agenda
    Route::get('/schedule', [ScheduleController::class, 'index']);
    Route::post('/schedule/block', [ScheduleController::class, 'block']);

    // Fase 4 - Carteira e Transações
    // Route::get('/wallet', [WalletController::class, 'show']);
    // Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
    // Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    // Route::get('/transactions', [TransactionController::class, 'index']);

    // Fase 5 - Reputação
    // Route::post('/ratings', [RatingController::class, 'store']);
    // Route::get('/ratings', [RatingController::class, 'index']);

    // Fase 6 - Administração
    // Route::prefix('admin')->group(function () {
    //     Route::get('/companies', [AdminController::class, 'companies']);
    //     Route::get('/professionals', [AdminController::class, 'professionals']);
    //     Route::get('/flinks', [AdminController::class, 'flinks']);
    //     Route::put('/block-user', [AdminController::class, 'blockUser']);
    //     Route::get('/logs', [AdminController::class, 'logs']);
    // });
});

