<?php

namespace Tests\Feature;

use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\Player;
use App\Models\User;
use App\Services\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPaymentOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'super-user'): User
    {
        Role::findOrCreate($role, 'web');

        return User::factory()->create()->assignRole($role);
    }

    private function payment(User $user, string $reference, float $amount): void
    {
        ClothingOrder::create(['user_id' => $user->id, 'event_id' => Event::factory()->create()->id,
            'player_id' => Player::factory()->create()->id, 'pay_status' => 1, 'payfast_paid' => true,
            'paid_at' => '2025-03-01', 'amount_paid' => $amount, 'total' => $amount,
            'payfast_pf_payment_id' => $reference]);
    }

    public function test_user_payment_overview_requires_super_user(): void
    {
        $target = User::factory()->create();
        $this->get(route('user.show', $target))->assertRedirect();
        foreach (['admin', 'convenor', 'player'] as $role) {
            $this->actingAs($this->actor($role))->get(route('user.show', $target))->assertForbidden();
        }
    }

    public function test_payer_totals_and_exports_are_scoped_to_selected_account_without_a_wallet(): void
    {
        $admin = $this->actor();
        $target = User::factory()->create();
        $this->payment($target, 'TARGET-PAID', 123.45);
        $this->payment($admin, 'OTHER-PAYER-PRIVATE', 999);
        $response = $this->actingAs($admin)->get(route('user.show', [$target, 'user_id' => $admin->id]))
            ->assertOk()->assertSee('TARGET-PAID')->assertDontSee('OTHER-PAYER-PRIVATE')
            ->assertSee('R 123.45')->assertViewHas('statement', fn ($data) => $data['paid'] === 12345 && $data['refunded'] === 0 && $data['net'] === 12345)
            ->assertSee(route('superadmin.account-statements.show', ['user' => $target, 'format' => 'pdf']), false)
            ->assertSee(route('superadmin.account-statements.show', ['user' => $target, 'format' => 'csv']), false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('clothing_orders', 2);
        $this->assertDatabaseCount('wallets', 0);
    }

    public function test_preview_is_limited_but_totals_include_all_records(): void
    {
        $target = User::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $this->payment($target, 'PREVIEW-'.$i, 10.01);
        }
        $response = $this->actingAs($this->actor())->get(route('user.show', $target))->assertOk()
            ->assertViewHas('statement', fn ($data) => $data['paid'] === 12012 && $data['rows']->count() === 12)
            ->assertSee('Showing the latest 10 records');
        $this->assertSame(10, substr_count($response->getContent(), 'PF PREVIEW-'));
        $this->assertDatabaseCount('clothing_orders', 12);
    }

    public function test_empty_and_oversized_accounts_keep_profile_and_statement_access(): void
    {
        $target = User::factory()->create();
        $this->actingAs($this->actor())->get(route('user.show', $target))->assertOk()
            ->assertSee('No recorded payments or completed refunds');
        $this->mock(AccountStatementService::class)->shouldReceive('build')->once()
            ->andThrow(ValidationException::withMessages(['from' => 'Too many records']));
        $this->get(route('user.show', $target))->assertOk()->assertSee('too many records for an all-time preview')
            ->assertSee('Full statement')->assertSee('Wallet Transactions')->assertViewHas('statement', null);
    }
}
