<?php

namespace Database\Factories;

use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'flink_id' => null,
            'type' => TransactionType::Deposit,
            'amount' => fake()->randomFloat(2, 50, 500),
            'status' => TransactionStatus::Completed,
        ];
    }
}
