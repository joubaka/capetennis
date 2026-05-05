<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamPaymentOrderFactory extends Factory
{
    protected $model = TeamPaymentOrder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'event_id' => Event::factory(),
            'total_amount' => $this->faker->randomFloat(2, 50, 500),
            'wallet_reserved' => 0,
            'payfast_amount_due' => 0,
            'wallet_debited' => 0,
            'payfast_paid' => 1,
            'pay_status' => 1,
            'payfast_pf_payment_id' => null,
            'payfast_raw_data' => null,
            'refund_method' => null,
            'refund_status' => null,
            'refund_gross' => null,
            'refund_fee' => null,
            'refund_net' => null,
            'refunded_at' => null,
            'refund_account_name' => null,
            'refund_bank_name' => null,
            'refund_account_number' => null,
            'refund_branch_code' => null,
            'refund_account_type' => null,
        ];
    }

    public function unpaid(): static
    {
        return $this->state([
            'payfast_paid' => 0,
            'wallet_debited' => 0,
            'pay_status' => 0,
        ]);
    }
}
