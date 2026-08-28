<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rede de segurança da dupla confirmação de conclusão do Flink — ver
// App\Console\Commands\AutoCompleteFlinks. Rodar a cada 15 min é granular o
// suficiente pro prazo (configurado em horas) sem sobrecarregar o worker.
// Requer `php artisan schedule:work` (dev) ou o cron do servidor chamando
// `php artisan schedule:run` a cada minuto (produção).
Schedule::command('flinks:auto-complete')->everyFifteenMinutes();
