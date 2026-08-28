<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Margem padrão da plataforma
    |--------------------------------------------------------------------------
    |
    | Percentual aplicado sobre o valor líquido informado pela empresa ao criar
    | um Flink (ver App\Domain\Flink\Services\PricingService). Fixo por enquanto
    | — ver docs/ARCHITECTURE.md para o racional de manter isso configurável.
    |
    */
    'platform_margin_percent' => (float) env('PLATFORM_DEFAULT_MARGIN_PERCENT', 7),

    /*
    |--------------------------------------------------------------------------
    | Raio de tolerância para check-in geolocalizado (Fase 3)
    |--------------------------------------------------------------------------
    */
    'checkin_radius_meters' => (int) env('FLINKER_CHECKIN_RADIUS_METERS', 150),

    /*
    |--------------------------------------------------------------------------
    | Prazo para conclusão automática do Flink
    |--------------------------------------------------------------------------
    |
    | A conclusão do Flink (e o split de pagamento) exige confirmação da empresa
    | E do profissional (ver App\Domain\Match\Actions\ConfirmCompletionAction).
    | Se só um dos dois confirmar e o outro nunca agir, o comando agendado
    | `flinks:auto-complete` completa automaticamente depois desse prazo (em
    | horas), contado a partir da primeira confirmação — evita que o pagamento
    | fique preso indefinidamente.
    |
    */
    'auto_complete_hours' => (int) env('FLINKER_AUTO_COMPLETE_HOURS', 48),

];
