<?php

namespace Database\Factories;

use App\Domain\Shared\Enums\UserProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profile' => UserProfile::Professional,
            'is_active' => true,
        ];
    }

    public function professional(): static
    {
        return $this->state(fn (array $attributes) => ['profile' => UserProfile::Professional]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => ['profile' => UserProfile::Company]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['profile' => UserProfile::Admin]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
