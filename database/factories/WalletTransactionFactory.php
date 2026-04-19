<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => $this->faker->randomElement(['credit', 'debit']),
            'amount' => $this->faker->randomFloat(2, 1, 500),
            'source_type' => 'order',
            'source_id' => $this->faker->numberBetween(1, 9999),
            'meta' => [],
        ];
    }

    public function credit(): static
    {
        return $this->state(['type' => 'credit']);
    }

    public function debit(): static
    {
        return $this->state(['type' => 'debit']);
    }
}
