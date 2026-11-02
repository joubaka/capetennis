<?php

namespace Tests\Feature;

use App\Models\{AuditEvent, Player, PlayerPlusAccessOverride, PlayerPlusMembership, PlayerPlusOrder, SiteSetting, User};
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusManagement, PlayerPlusService, PlayerRatingAccess};
use App\Support\Audit\AuditWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, File, Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerPlusManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Bus::fake();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
    }

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    private function changeData(string $action = 'grant', int $version = 0): array
    {
        return ['action' => $action, 'version' => $version, 'request_token' => (string) Str::uuid(), 'confirm' => '1',
            'reason' => 'Verified support correction reference TEST-1', 'starts_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addYear()->format('Y-m-d\TH:i')];
    }

    private function export(string $name, $response): void
    {
        if (getenv('CT_PLUS_MANAGEMENT_QA_HTML') !== '1') return;
        File::ensureDirectoryExists(storage_path('framework/testing'));
        file_put_contents(storage_path('framework/testing/player-plus-management-'.$name.'.html'), $response->getContent());
    }

    public function test_management_routes_require_super_user_and_do_not_leak_players(): void
    {
        $player = Player::factory()->create();
        $url = route('superadmin.player-plus.manage.show', $player);
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->post(route('superadmin.player-plus.manage.update', $player), $this->changeData())->assertForbidden();
        $order = app(PlayerPlusService::class)->start($player, User::factory()->create(), (string) Str::uuid());
        $this->post(route('superadmin.player-plus.manage.order-action', [$player, $order]), ['action' => 'close', 'reason' => 'Attempt unauthorised closure', 'confirm' => 1])->assertForbidden();
        $this->assertDatabaseCount('player_plus_access_overrides', 0);
    }

    public function test_manual_grant_is_linked_player_access_only_and_never_fabricates_money(): void
    {
        $owner = User::factory()->create();
        $parent = User::factory()->create();
        $outsider = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $parent->players()->attach($player);
        $other = Player::factory()->create(['userId' => $owner->id]);
        $admin = $this->admin();
        $data = $this->changeData();
        app(PlayerPlusManagement::class)->change($player, $admin, $data);
        app(PlayerPlusManagement::class)->change($player, $admin, $data);
        $access = app(PlayerPlusEntitlement::class);
        $this->assertTrue($access->forPlayer($owner, $player));
        $this->assertTrue($access->forPlayer($parent, $player));
        $this->assertTrue($access->discovery($parent));
        $this->assertFalse($access->forPlayer($outsider, $player));
        $this->assertFalse($access->forPlayer($owner, $other));
        $this->assertDatabaseCount('player_plus_orders', 0);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, app(PlayerPlusManagement::class)->history($player)->count());
        $this->assertSame($admin->id, app(PlayerPlusManagement::class)->history($player)->first()->actor_id);
        $this->assertSame(1, PlayerPlusAccessOverride::sole()->version);
        $parent->players()->detach($player);
        $this->assertFalse($access->discovery($parent));
    }

    public function test_suspend_restore_and_remove_grant_respect_dates_and_preserve_paid_history(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $service = app(PlayerPlusService::class);
        $order = $service->start($player, $owner, (string) Str::uuid());
        $service->handoff($order, $owner);
        $itn = ['custom_str5' => 'PlayerPlus', 'custom_int2' => $player->id, 'custom_int4' => $owner->id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => PlayerPlusService::amount($order->price_cents), 'pf_payment_id' => 'management-paid'];
        app(PlayerPlusManagement::class)->change($player, $admin, $this->changeData('suspend'));
        $service->receive($itn);
        $this->assertFalse(app(PlayerPlusEntitlement::class)->forPlayer($owner, $player));
        $paid = $order->fresh()->getAttributes();
        app(PlayerPlusManagement::class)->change($player, $admin, $this->changeData('restore', 1));
        $this->assertTrue(app(PlayerPlusEntitlement::class)->forPlayer($owner, $player));
        $this->assertSame($paid, $order->fresh()->getAttributes());
        $this->travelTo($order->fresh()->term_ends_at);
        $this->assertFalse(app(PlayerPlusEntitlement::class)->forPlayer($owner, $player));
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_stale_access_changes_and_invalid_dates_fail_without_audit_or_mutation(): void
    {
        $admin = $this->admin();
        $player = Player::factory()->create();
        app(PlayerPlusManagement::class)->change($player, $admin, $this->changeData());
        $this->actingAs($admin)->post(route('superadmin.player-plus.manage.update', $player), $this->changeData('suspend'))->assertSessionHasErrors('version');
        $this->post(route('superadmin.player-plus.manage.update', $player), array_replace($this->changeData('grant', 1), ['ends_at' => now()->subDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors();
        $this->assertSame('grant', PlayerPlusAccessOverride::sole()->mode);
        $this->assertSame(1, app(PlayerPlusManagement::class)->history($player)->count());
    }

    public function test_audit_failure_rolls_back_manual_access(): void
    {
        $writer = Mockery::mock(AuditWriter::class);
        $writer->shouldReceive('record')->once()->andReturnNull();
        $this->app->instance(AuditWriter::class, $writer);
        try {
            app(PlayerPlusManagement::class)->change(Player::factory()->create(), $this->admin(), $this->changeData());
            $this->fail('Unaudited access saved.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('audited', $e->getMessage());
        }
        $this->assertDatabaseCount('player_plus_access_overrides', 0);
    }

    public function test_purchase_and_handoff_reject_manual_covered_or_deactivated_players(): void
    {
        $player = Player::factory()->create();
        $payer = User::factory()->create();
        $service = app(PlayerPlusService::class);
        $order = $service->start($player, $payer, (string) Str::uuid());
        app(PlayerPlusManagement::class)->change($player, $this->admin(), $this->changeData());
        try { $service->handoff($order, $payer); $this->fail('Covered player payment allowed.'); } catch (ValidationException) {}
        try { $service->start($player, $payer, (string) Str::uuid()); $this->fail('Covered player checkout allowed.'); } catch (ValidationException) {}
        $this->assertNull($order->fresh()->payfast_handed_off_at);
        $this->assertDatabaseCount('player_plus_orders', 1);
    }

    public function test_close_unpaid_checkout_is_audited_idempotent_and_cannot_close_payment_window(): void
    {
        $admin = $this->admin();
        $service = app(PlayerPlusService::class);
        $payer = User::factory()->create();
        $order = $service->start(Player::factory()->create(), $payer, (string) Str::uuid());
        $price = $order->price_cents;
        $service->closeUnpaidAsAdmin($order, $admin, 'Support corrected stale unpaid checkout');
        $service->closeUnpaidAsAdmin($order, $admin, 'Support corrected stale unpaid checkout');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame($price, $order->fresh()->price_cents);
        $this->assertSame(1, AuditEvent::where('action', 'player-plus.admin-checkout-closed')->count());
        $other = $service->start(Player::factory()->create(), $payer, (string) Str::uuid());
        $service->handoff($other, $payer);
        try { $service->closeUnpaidAsAdmin($other, $admin, 'Attempt close in payment window'); $this->fail('Live payment closed.'); } catch (ValidationException) {}
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $inconsistent = $service->start(Player::factory()->create(), $payer, (string) Str::uuid());
        $inconsistent->update(['wallet_debited' => true]);
        try { $service->closeUnpaidAsAdmin($inconsistent, $admin, 'Investigate inconsistent debit evidence'); $this->fail('Debited order closed.'); } catch (ValidationException) {}
        $this->assertSame('pending', $inconsistent->fresh()->status);
    }

    public function test_management_pages_search_filter_paginate_and_render_fixtures(): void
    {
        $admin = $this->admin();
        $player = Player::factory()->create(['name' => 'Management', 'surname' => 'Example']);
        $this->actingAs($admin);
        $index = route('superadmin.player-plus.manage.index');
        $empty = $this->get($index)->assertOk()->assertSee('No players match');
        $this->export('empty', $empty);
        $order = app(PlayerPlusService::class)->start($player, User::factory()->create(), (string) Str::uuid());
        app(PlayerPlusManagement::class)->change($player, $admin, $this->changeData());
        $response = $this->get($index)->assertOk()->assertSee('Management Example');
        $this->export('index', $response);
        $this->get($index.'?q=P'.$order->id)->assertOk()->assertSee('Management Example');
        $this->get($index.'?q='.urlencode($order->user->email))->assertOk()->assertSee('Management Example');
        $this->get($index.'?status=suspended')->assertOk()->assertDontSee('Management Example');
        $detail = $this->get(route('superadmin.player-plus.manage.show', $player))->assertOk()->assertSee('Access active')->assertSee('Close unpaid checkout');
        $this->export('detail', $detail);
        $this->post(route('superadmin.player-plus.manage.update', $player), ['action' => 'grant'])->assertSessionHasErrors();
        $validation = $this->get(route('superadmin.player-plus.manage.show', $player))->assertOk()->assertSee('The change was not saved.');
        $this->export('validation', $validation);
    }

    public function test_subscription_admin_stop_and_retry_are_attributed_and_cross_player_actions_fail(): void
    {
        $admin = $this->admin();
        $payer = User::factory()->create();
        $service = app(PlayerPlusService::class);
        $order = $service->start(Player::factory()->create(), $payer, (string) Str::uuid());
        $subscription = \App\Models\PlayerPlusSubscription::create(['order_id' => $order->id, 'provider_token' => 'synthetic-management-token',
            'provider_token_hash' => hash('sha256', 'synthetic-management-token'), 'status' => 'active', 'billing_anchor_at' => now(), 'next_billing_at' => now()->addMonth()]);
        $gateway = Mockery::mock(\App\Services\Performance\PlayerPlusSubscriptionGateway::class);
        $gateway->shouldReceive('cancel')->twice()->with('synthetic-management-token')->andReturn(false, true);
        $this->app->instance(\App\Services\Performance\PlayerPlusSubscriptionGateway::class, $gateway);
        $originalOrder = $order->fresh()->getAttributes();
        $this->actingAs($admin);
        $other = Player::factory()->create();
        $this->post(route('superadmin.player-plus.manage.order-action', [$other, $order]), ['action' => 'stop-renewal', 'confirm' => 1, 'reason' => 'Verified customer cancellation request'])->assertNotFound();
        $url = route('superadmin.player-plus.manage.order-action', [$order->player, $order]);
        $this->post($url, ['action' => 'stop-renewal', 'confirm' => 1, 'reason' => 'Verified customer cancellation request'])->assertSessionHasErrors();
        $this->assertSame('cancel_pending', $subscription->fresh()->status);
        $this->post($url, ['action' => 'stop-renewal', 'confirm' => 1, 'reason' => 'Retry provider cancellation TEST-2'])->assertRedirect();
        $this->post($url, ['action' => 'stop-renewal', 'confirm' => 1, 'reason' => 'Repeated provider cancellation TEST-2'])->assertRedirect();
        $this->assertNotNull($subscription->fresh()->cancelled_at);
        $this->assertSame($originalOrder, $order->fresh()->getAttributes());
        $this->get(route('superadmin.player-plus.manage.show', $order->player))->assertOk()->assertDontSee('synthetic-management-token');
        $attempts = AuditEvent::where('action', 'player-plus.subscription-admin-cancellation-attempt')->get();
        $this->assertCount(2, $attempts);
        $this->assertSame([$admin->id], $attempts->pluck('actor_id')->unique()->all());
        $this->assertSame('Retry provider cancellation TEST-2', AuditEvent::where('action', 'player-plus.subscription-cancelled')->sole()->reason);
        $foreign = $service->start($other, $payer, (string) Str::uuid());
        DB::table('player_plus_orders')->where('id', $foreign->id)->update(['subscription_order_id' => $order->id]);
        $this->post(route('superadmin.player-plus.manage.order-action', [$other, $foreign]), ['action' => 'stop-renewal', 'confirm' => 1, 'reason' => 'Reject inconsistent subscription relation'])->assertNotFound();
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }

    public function test_scheduled_expired_and_removed_grants_and_cross_player_replay(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $other = Player::factory()->create(['userId' => $owner->id]);
        $data = array_replace($this->changeData(), ['starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i')]);
        $management = app(PlayerPlusManagement::class);
        $management->change($player, $admin, $data);
        $management->change($other, $admin, $data);
        $this->assertDatabaseCount('player_plus_access_overrides', 2);
        $access = app(PlayerPlusEntitlement::class);
        $this->assertFalse($access->forPlayer($owner, $player));
        $this->travelTo(\Carbon\CarbonImmutable::parse($data['starts_at']));
        $this->assertTrue($access->forPlayer($owner, $player));
        $management->change($player, $admin, $this->changeData('inherit', 1));
        $this->assertFalse($access->forPlayer($owner, $player));
        $this->assertTrue($access->forPlayer($owner, $other));
        $this->travelTo(\Carbon\CarbonImmutable::parse($data['ends_at']));
        $this->assertFalse($access->forPlayer($owner, $other));
    }

    public function test_deactivation_survives_verified_recurring_renewal(): void
    {
        SiteSetting::set(PlayerPlusService::MONTHLY_ENABLED_SETTING, '1');
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'synthetic-management',
            'services.payfast.sandbox_key' => 'synthetic-key', 'services.payfast.passphrase_sandbox' => 'synthetic-passphrase']);
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $service = app(PlayerPlusService::class);
        $order = $service->start($player, $owner, (string) Str::uuid(), PlayerPlusService::monthlyPriceCents(), 'monthly', true);
        $service->handoff($order, $owner);
        $itn = ['custom_str5' => 'PlayerPlus', 'custom_int2' => $player->id, 'custom_int4' => $owner->id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => PlayerPlusService::amount($order->price_cents),
            'pf_payment_id' => 'management-monthly-1', 'token' => 'synthetic-recurring-management'];
        $service->receive($itn);
        $admin = $this->admin();
        app(PlayerPlusManagement::class)->change($player, $admin, $this->changeData('suspend'));
        $this->travelTo($order->fresh()->term_ends_at);
        $service->receive(array_replace($itn, ['pf_payment_id' => 'management-monthly-2']));
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertDatabaseCount('player_plus_orders', 2);
        $this->assertFalse(app(PlayerPlusEntitlement::class)->forPlayer($owner, $player));
        app(PlayerPlusManagement::class)->change($player, $admin, $this->changeData('restore', 1));
        $this->assertTrue(app(PlayerPlusEntitlement::class)->forPlayer($owner, $player));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
