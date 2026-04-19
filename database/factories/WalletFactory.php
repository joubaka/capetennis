<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'payable_type' => User::class,
            'payable_id' => User::factory(),
        ];
    }

    /**
     * Wallet belonging to a specific user model.
     */
    public function forUser(User $user): static
    {
        return $this->state([
            'payable_type' => User::class,
            'payable_id' => $user->id,
        ]);
    }
}
