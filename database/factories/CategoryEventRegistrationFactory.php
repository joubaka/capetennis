<?php

namespace Database\Factories;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryEventRegistrationFactory extends Factory
{
    protected $model = CategoryEventRegistration::class;

    public function definition(): array
    {
        return [
            'category_event_id' => CategoryEvent::factory(),
            'registration_id' => Registration::factory(),
            'user_id' => User::factory(),
            'pf_transaction_id' => null,
            'payment_status_id' => null,
            'status' => 'active',
            'withdrawn_at' => null,
            'refund_method' => null,
            'refund_status' => 'not_refunded',
            'refund_gross' => 0,
            'refund_fee' => 0,
            'refund_net' => 0,
            'refunded_at' => null,
            'refund_account_name' => null,
            'refund_bank_name' => null,
            'refund_account_number' => null,
            'refund_branch_code' => null,
            'refund_account_type' => null,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
            'refund_status' => 'not_refunded',
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'pf_transaction_id' => $this->faker->uuid(),
            'payment_status_id' => 1,
        ]);
    }

    public function pendingRefund(): static
    {
        return $this->withdrawn()->paid()->state([
            'refund_status' => 'pending',
            'refund_method' => 'bank',
        ]);
    }
}
