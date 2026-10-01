<?php

namespace App\Domain\Flink\Services;

use App\Domain\Settings\Services\SettingsService;

class PricingService
{
    /**
     * Calcula a margem e o valor total cobrado da empresa a partir do valor
     * líquido que ela deseja pagar ao profissional.
     *
     * Achado #15 (auditoria 2026-10-01): a margem agora é configurável em runtime
     * pelo painel Filament (ver SettingsService / Filament\Resources\Settings),
     * caindo pro valor fixo de `config('flinker.platform_margin_percent')` se a
     * configuração ainda não existir no banco. Resolvemos o SettingsService via
     * `app()` em vez de injetar no construtor de propósito: os testes unitários
     * (PricingServiceTest) instanciam `new PricingService()` direto, sem passar
     * pelo container — um construtor com dependência obrigatória quebraria isso.
     */
    public function calculate(float $netValue): array
    {
        $marginPercent = app(SettingsService::class)->getFloat(
            'platform_margin_percent',
            (float) config('flinker.platform_margin_percent'),
        );

        $margin = round($netValue * ($marginPercent / 100), 2);
        $total = round($netValue + $margin, 2);

        return [
            'net_value' => round($netValue, 2),
            'platform_margin' => $margin,
            'total_value' => $total,
            'margin_percent' => $marginPercent,
        ];
    }
}
