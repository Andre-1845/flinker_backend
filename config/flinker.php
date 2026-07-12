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

];
