<?php

namespace App\Domain\Flink\Services;

class PricingService
{
    /**
     * Calcula a margem e o valor total cobrado da empresa a partir do valor
     * líquido que ela deseja pagar ao profissional.
     *
     * Regra atual (MVP): margem fixa configurável via `config('flinker.platform_margin_percent')`.
     * Nenhum outro lugar do código deve calcular a margem diretamente — sempre
     * passar por aqui, para que trocar por uma regra dinâmica (por categoria,
     * por volume, por região) no futuro não exija tocar em outras partes do sistema.
     *
     * @return array{net_value: float, platform_margin: float, total_value: float, margin_percent: float}
     */
    public function calculate(float $netValue): array
    {
        $marginPercent = config('flinker.platform_margin_percent');

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
