<?php

namespace Database\Factories;

use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Professional\Models\Professional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlinkMatch>
 */
class FlinkMatchFactory extends Factory
{
    protected $model = FlinkMatch::class;

    public function definition(): array
    {
        return [
            'flink_id' => Flink::factory(),
            'professional_id' => Professional::factory(),
            'status' => MatchStatus::Pending,
        ];
    }
}
