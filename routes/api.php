<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FlinkController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MercadoPagoWebhookController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
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
// Rate limit dedicado (não o `throttle:api` global) — protege contra força bruta de
// senha e spam de cadastro. 6 tentativas/minuto por IP é folgado pra uso legítimo.
Route::prefix('auth')->middleware('throttle:6,1')->group(function () {
    Route::post('/register/professional', [AuthController::class, 'registerProfessional']);
    Route::post('/register/company', [AuthController::class, 'registerCompany']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Fase 4 - Webhook do Mercado Pago (público — o Mercado Pago não tem token Sanctum).
// Rate limit generoso (o Mercado Pago pode reenviar notificações), só pra evitar abuso.
Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->middleware('throttle:30,1');

// Rotas autenticadas (todas as demais, protegidas por Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    // Reenvio do e-mail de confirmação de conta (o link em si é público e fica em
    // routes/web.php — quem clica no e-mail não tem token Sanctum).
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');

    // Fase 1 - Usuários
    Route::get('/users/me', [UserController::class, 'me']);
    Route::put('/users/me', [UserController::class, 'update']);

    // Fase 1 - Profissionais
    Route::apiResource('professionals', ProfessionalController::class)->only(['index', 'show', 'update']);
    // Achado auditoria 2026-10-01: upload de foto nunca existiu no backend — o
    // botão de câmera do frontend só gerava uma prévia local, sem persistir nada.
    Route::post('/professionals/{professional}/photo', [ProfessionalController::class, 'uploadPhoto']);
    // Achado de perfil mockado (02/10/2026): perfil público de verdade, sem
    // dados sensíveis — ver ProfessionalController::publicProfile().
    Route::get('/professionals/{professional}/public-profile', [ProfessionalController::class, 'publicProfile']);

    // Fase 1 - Empresas
    Route::apiResource('companies', CompanyController::class)->only(['index', 'show', 'update']);
    // Achado 02/10/2026: paridade com o upload de foto do profissional.
    Route::post('/companies/{company}/photo', [CompanyController::class, 'uploadPhoto']);
    Route::get('/companies/{company}/public-profile', [CompanyController::class, 'publicProfile']);

    // Fase 2 - Flinks
    Route::get('/flinks/active', [FlinkController::class, 'active']);
    Route::get('/flinks/company/{company}', [FlinkController::class, 'byCompany']);
    Route::put('/flinks/{flink}/complete', [FlinkController::class, 'complete']);
    Route::apiResource('flinks', FlinkController::class);

    // Fase 3 - Matches
    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::put('/matches/{match}/accept', [MatchController::class, 'accept']);
    Route::put('/matches/{match}/confirm', [MatchController::class, 'confirm']);
    Route::post('/matches/{match}/checkin', [MatchController::class, 'checkin']);
    // Conclusão dupla (Fase 4 revisada): profissional confirma aqui, empresa confirma
    // em PUT /flinks/{id}/complete — o split de pagamento só roda com os dois confirmados.
    Route::put('/matches/{match}/confirm-completion', [MatchController::class, 'confirmCompletion']);
    Route::put('/matches/{match}/cancel', [MatchController::class, 'cancel']);

    // Achado #6 (auditoria 2026-10-01): chat 100% mock no frontend, sem
    // nenhuma infraestrutura no backend. Liberado só com match confirmado
    // (aceite mutuo) — ver MessageController::ensureChatUnlocked().
    Route::get('/matches/conversations-summary', [MessageController::class, 'conversationsSummary']);
    Route::get('/matches/{match}/messages', [MessageController::class, 'index']);
    Route::post('/matches/{match}/messages', [MessageController::class, 'store']);
    Route::post('/matches/{match}/messages/read', [MessageController::class, 'markRead']);

    // Achado de perfil mockado (02/10/2026): avaliações bilaterais de verdade,
    // liberadas só depois do flink concluído — ver
    // RatingController::ensureRatingUnlocked().
    Route::post('/matches/{match}/ratings', [RatingController::class, 'store']);
    Route::put('/ratings/{rating}/visibility', [RatingController::class, 'toggleVisibility']);

    // Fase 3 - Agenda
    Route::get('/schedule', [ScheduleController::class, 'index']);
    Route::post('/schedule/block', [ScheduleController::class, 'block']);

    // Fase 4 - Carteira e Transações
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    // ⚠️ Só funciona com APP_ENV=local — ver aviso no WalletController::devTopup()
    Route::post('/wallet/dev-topup', [WalletController::class, 'devTopup']);

    // Achado #15 (auditoria 2026-10-01): margem da plataforma configurável via
    // Filament (ver SettingsService) — o frontend precisa saber o valor atual
    // em vez de ter o percentual hardcoded (estava errado: 8% vs 7% real).
    Route::get('/settings/platform-margin', [SettingController::class, 'platformMargin']);

    // Fase 5 - Reputação: implementada em 02/10/2026, ver
    // /matches/{match}/ratings e /ratings/{rating}/visibility acima.

    // Fase 6 - Administração
    // Route::prefix('admin')->group(function () {
    //     Route::get('/companies', [AdminController::class, 'companies']);
    //     Route::get('/professionals', [AdminController::class, 'professionals']);
    //     Route::get('/flinks', [AdminController::class, 'flinks']);
    //     Route::put('/block-user', [AdminController::class, 'blockUser']);
    //     Route::get('/logs', [AdminController::class, 'logs']);
    // });
});
