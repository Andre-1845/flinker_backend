<?php

namespace Database\Factories;

use App\Domain\Professional\Models\Professional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    protected $model = Professional::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->professional(),
            'cpf' => fake()->numerify('###########'),
            'phone' => fake()->numerify('(##) #####-####'),
            'address' => fake()->address(),
            'pix_key' => fake()->uuid(),
            'photo_url' => null,
            'is_mei' => false,
            'cnpj' => null,
            'reputation' => fake()->randomFloat(2, 3, 5),
        ];
    }

    public function mei(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mei' => true,
            'cnpj' => fake()->numerify('##############'),
        ]);
    }
}
