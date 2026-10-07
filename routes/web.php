<?php

use App\Http\Controllers\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Link do e-mail de confirmação de conta. Público (quem clica não está logado) e
// protegido pela assinatura da URL, que expira em 60 minutos. O nome
// `verification.verify` é o que a notificação VerifyEmail do Laravel procura.
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware('throttle:6,1')
    ->name('verification.verify');
