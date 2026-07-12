<?php

namespace Database\Factories;

use App\Domain\Company\Models\Company;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flink>
 */
class FlinkFactory extends Factory
{
    protected $model = Flink::class;

    public function definition(): array
    {
        $netValue = fake()->randomFloat(2, 100, 500);
        $marginPercent = config('flinker.platform_margin_percent');
        $margin = round($netValue * ($marginPercent / 100), 2);

        $start = fake()->dateTimeBetween('+1 day', '+2 weeks');

        return [
            'company_id' => Company::factory(),
            'activity_type' => fake()->randomElement(['Garçom', 'Segurança', 'Montador de estande', 'Recepcionista']),
            'location' => fake()->address(),
            'latitude' => fake()->latitude(-23.6, -22.7), // faixa aproximada do RJ, ajustável
            'longitude' => fake()->longitude(-44.5, -43.0),
            'start_date_time' => $start,
            'end_date_time' => (clone $start)->modify('+6 hours'),
            'requirements' => fake()->sentence(),
            'status' => FlinkStatus::Open,
            'net_value' => $netValue,
            'platform_margin' => $margin,
            'total_value' => round($netValue + $margin, 2),
        ];
    }

    public function withStatus(FlinkStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
