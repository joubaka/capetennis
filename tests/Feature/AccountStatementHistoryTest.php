<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamPaymentOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountStatementHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function entry(User $user, Player $player, CategoryEvent $category, ?string $reference = null): CategoryEventRegistration
    {
        $registration = Registration::create();
        $registration->players()->attach($player);

        return CategoryEventRegistration::create(['user_id' => $user->id, 'registration_id' => $registration->id,
            'category_event_id' => $category->id, 'payment_status_id' => 1, 'pf_transaction_id' => $reference]);
    }

    private function receipt(User $user, Player $player, Event $event, array $attributes = []): Transaction
    {
        return Transaction::unguarded(fn () => Transaction::create($attributes + [
            'custom_int4' => $user->id, 'player_id' => $player->id, 'custom_int2' => $player->id,
            'event_id' => $event->id, 'custom_int3' => $event->id, 'pf_payment_id' => fake()->unique()->numerify('LEGACY-########'),
            'amount_gross' => 450, 'payment_status' => null, 'is_test' => false,
            'created_at' => '2019-04-05 12:00:00', 'updated_at' => '2019-04-05 12:00:00']));
    }

    public function test_all_time_includes_old_paid_entries_and_clothing_without_inventing_amount_or_date(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['name' => 'Mia', 'surname' => 'Example']);
        $event = Event::factory()->create(['start_date' => '2019-04-01']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $this->entry($user, $player, $category, 'RECEIPT-NOT-RETAINED');
        ClothingOrder::create(['user_id' => $user->id, 'player_id' => $player->id, 'event_id' => $event->id,
            'pay_status' => 1, 'total' => 0, 'amount_paid' => 0, 'paid_at' => null]);
        $data = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31', '', 'all', true);
        $this->assertCount(2, $data['historicalRows']);
        $this->assertSame([null, null], $data['historicalRows']->pluck('amount')->all());
        $this->assertSame([null, null], $data['historicalRows']->pluck('date')->all());
        $this->assertSame(0, $data['paid']);
        $period = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31', 'Mia');
        $this->assertCount(2, $period['historicalRows']);
        $this->assertCount(0, app(AccountStatementService::class)->build(User::factory()->create(), '2025-01-01', '2026-12-31')['historicalRows']);
        $this->actingAs($user)->get(route('account.statement'))->assertOk()->assertViewHas('allTime', true)
            ->assertSee('Amount unavailable')->assertSee('Payment date unavailable')->assertSee('Known payments');
        $this->get(route('account.statement', ['all_time' => 1, 'format' => 'pdf']))->assertOk();
    }

    public function test_legacy_order_registration_receipt_requires_matching_team_roster_and_counts_old_money_once(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'pay_status' => 1]);
        $base = ['custom_str5' => 'Order', 'transaction_type' => 'Registration', 'custom_int1' => $team->id];
        $this->receipt($user, $player, $event, $base + ['pf_payment_id' => 'LEGACY-VALID', 'player_id' => null]);
        $this->receipt($user, $player, $event, ['custom_int3' => $event->id + 999] + $base);
        $this->receipt($user, $player, $event, ['custom_int2' => $player->id + 999] + $base);
        $this->receipt($user, $player, $event, ['custom_int1' => 999999] + $base);
        $this->receipt($user, $player, $event, ['payment_status' => 'FAILED'] + $base);
        $this->receipt($user, $player, $event, ['is_test' => true] + $base);
        $this->receipt($user, $player, $event, ['archived_at' => now()] + $base);
        $this->receipt($user, $player, $event, ['custom_int4' => User::factory()->create()->id] + $base);
        $this->receipt($user, $player, $event, ['custom_str5' => 'TeamOrder', 'custom_int5' => 999999]);
        $all = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31', '', 'all', true);
        $this->assertSame(45000, $all['paid']);
        $this->assertCount(1, $all['rows']);
        $this->assertSame(0, app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31')['paid']);
    }

    public function test_shared_legacy_reference_has_one_amount_both_players_and_no_false_undated_history_outside_period(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $mia = Player::factory()->create(['name' => 'Mia']);
        $dawid = Player::factory()->create(['name' => 'Dawid']);
        $this->entry($user, $mia, $category, 'SHARED-PF');
        $this->entry($user, $dawid, $category, 'SHARED-PF');
        $this->receipt($user, $mia, $event, ['pf_payment_id' => 'SHARED-PF', 'payment_status' => 'COMPLETE', 'category_event_id' => $category->id]);
        $all = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31', 'Dawid', 'all', true);
        $this->assertSame(45000, $all['paid']);
        $this->assertCount(1, $all['rows']);
        $this->assertCount(2, $all['rows'][0]['details']);
        $this->assertCount(0, $all['historicalRows']);
        $period = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31');
        $this->assertCount(0, $period['rows']);
        $this->assertCount(0, $period['historicalRows']);
    }

    public function test_stale_modern_pointer_remains_visible_as_unknown_and_admin_exports_target_history(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();
        $category = CategoryEvent::factory()->create();
        $entry = $this->entry($user, $player, $category, 'STALE-POINTER');
        $order = RegistrationOrder::create(['user_id' => $user->id, 'pay_status' => true, 'payfast_pf_payment_id' => 'STALE-POINTER']);
        RegistrationOrderItems::unguarded(fn () => RegistrationOrderItems::create(['order_id' => $order->id,
            'registration_id' => $entry->registration_id, 'category_event_id' => $category->id, 'player_id' => $player->id, 'item_price' => 999]));
        $data = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31');
        $this->assertCount(1, $data['historicalRows']);
        $this->assertNull($data['historicalRows'][0]['amount']);
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $csv = $this->actingAs($admin)->get(route('superadmin.account-statements.show', ['user' => $user, 'all_time' => 1, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString($user->email, $csv);
        $this->assertStringContainsString('STALE-POINTER', $csv);
        $this->assertStringContainsString('Amount unavailable', $csv);
        $this->assertStringContainsString('Payment date unavailable', $csv);
        $this->assertStringNotContainsString('999.00', $csv);
    }

    public function test_partial_clothing_with_known_payment_date_respects_period_while_undated_records_remain_visible(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();
        $event = Event::factory()->create();
        foreach (['2019-02-01', '2025-02-01', null] as $date) {
            ClothingOrder::create(['user_id' => $user->id, 'player_id' => $player->id, 'event_id' => $event->id,
                'pay_status' => 1, 'paid_at' => $date, 'amount_paid' => 0, 'total' => 0]);
        }
        $period = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31');
        $this->assertCount(2, $period['historicalRows']);
        $this->assertSame(['2025-02-01 00:00:00', null], $period['historicalRows']->pluck('date')->all());
        $this->assertCount(3, app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31', '', 'all', true)['historicalRows']);
    }

    public function test_paid_team_order_without_receipt_remains_unknown_and_private_to_payer(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $player = Player::factory()->create();
        $event = Event::factory()->create();
        TeamPaymentOrder::create(['user_id' => $user->id, 'event_id' => $event->id, 'player_id' => $player->id,
            'pay_status' => true, 'total_amount' => 1000, 'payfast_pf_payment_id' => 'NO-RECEIPT']);
        TeamPaymentOrder::create(['user_id' => $user->id, 'event_id' => $event->id, 'player_id' => $player->id,
            'pay_status' => true, 'total_amount' => 800, 'collection_status' => 'reconciliation_required']);
        $data = app(AccountStatementService::class)->build($user, '2025-01-01', '2026-12-31');
        $this->assertCount(1, $data['historicalRows']);
        $this->assertNull($data['historicalRows'][0]['amount']);
        $this->assertNull($data['historicalRows'][0]['date']);
        $this->assertSame(0, $data['paid']);
        $this->assertCount(0, app(AccountStatementService::class)->build($other, '2025-01-01', '2026-12-31')['historicalRows']);
    }
}
