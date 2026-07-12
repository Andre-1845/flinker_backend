<?php

namespace Database\Factories;

use App\Domain\Company\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->company(),
            'cnpj' => fake()->numerify('##############'),
            'responsible_name' => fake()->name(),
            'responsible_cpf' => fake()->numerify('###########'),
            'phone' => fake()->numerify('(##) #####-####'),
            'address' => fake()->address(),
            'pix_key' => fake()->uuid(),
            'reputation' => fake()->randomFloat(2, 3, 5),
        ];
    }
}
