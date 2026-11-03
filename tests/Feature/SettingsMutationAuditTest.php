<?php

namespace Tests\Feature;

use App\Models\{AuditEvent, Player, PlayerPlusOrder, SiteSetting, User};
use App\Services\Performance\PlayerPlusService;
use App\Services\SettingsMutationService;
use App\Support\Audit\{AuditIntegrity, AuditWriter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsMutationAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['audit.enabled' => true, 'cache.default' => 'array']);
        Role::findOrCreate('super-user', 'web');
        $this->admin = User::factory()->create()->assignRole('super-user');
    }

    public function test_toggle_has_actor_exact_old_new_values_and_integrity_and_repeat_is_a_noop(): void
    {
        SiteSetting::set('registration_open', '1', SiteSetting::GROUP_REGISTRATION);
        $this->actingAs($this->admin)->postJson(route('settings.store.single'), ['key' => 'registration_open', 'value' => '0'])->assertOk();
        $record = AuditEvent::where('action', 'settings.updated')->sole();
        $this->assertSame($this->admin->id, $record->actor_id);
        $this->assertTrue($record->before['registration_open']['exists']);
        $this->assertSame('1', $record->before['registration_open']['value']);
        $this->assertTrue($record->after['registration_open']['exists']);
        $this->assertSame('0', $record->after['registration_open']['value']);
        $this->assertTrue(AuditIntegrity::verify($record->getRawOriginal()));
        $this->actingAs($this->admin)->postJson(route('settings.store.single'), ['key' => 'registration_open', 'value' => '0'])->assertOk();
        $this->assertSame(1, AuditEvent::where('action', 'settings.updated')->count());
    }

    public function test_bulk_save_is_one_atomic_operation_with_true_before_values(): void
    {
        SiteSetting::set('registration_open', '1', SiteSetting::GROUP_REGISTRATION);
        $payload = $this->bulkPayload() + ['withdrawal_deadline_days' => '12', 'admin_notification_email' => 'private-ops@example.test'];
        $this->actingAs($this->admin)->postJson(route('settings.store'), $payload)->assertOk();
        $record = AuditEvent::where('action', 'settings.updated')->sole();
        $this->assertSame('1', $record->before['registration_open']['value']);
        $this->assertSame('0', $record->after['registration_open']['value']);
        $this->assertSame('12', SiteSetting::get('withdrawal_deadline_days'));
        $this->assertGreaterThan(10, $record->metadata['changed_count']);
        $this->assertStringNotContainsString('private-ops@example.test', $this->allAuditJson());
    }

    public function test_template_and_legal_content_are_hashed_in_business_history_and_absent_from_request_logs(): void
    {
        $subject = 'Private synthetic template subject 7391';
        $body = 'Private synthetic template body 4823';
        $content = '<p>Private synthetic legal content 9017</p>';
        $this->actingAs($this->admin)->postJson(route('settings.store.template'), ['type' => 'registration', 'subject' => $subject, 'body' => $body])->assertOk();
        $this->actingAs($this->admin)->postJson(route('settings.store.content'), ['key' => 'terms_conditions_content', 'content' => $content])->assertOk();
        $this->assertSame($body, SiteSetting::get('player_email_body_registration'));
        $event = AuditEvent::where('action', 'settings.updated')->orderByDesc('id')->firstOrFail();
        $this->assertSame(hash('sha256', $content), $event->after['terms_conditions_content']['value']['sha256']);
        $this->assertSame(strlen($content), $event->after['terms_conditions_content']['value']['bytes']);
        foreach ([$subject, $body, $content] as $raw) {
            $this->assertStringNotContainsString($raw, $this->allAuditJson());
        }
        $this->assertSame(2, AuditEvent::where('action', 'settings.updated')->count());
    }

    public function test_denied_and_invalid_settings_requests_do_not_log_raw_submitted_content(): void
    {
        $raw = 'Synthetic content must never enter request audit 5532';
        $this->actingAs(User::factory()->create())->postJson(route('settings.store.content'), ['key' => 'terms_conditions_content', 'content' => $raw])->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('settings.store.template'), ['type' => 'invalid', 'subject' => $raw, 'body' => $raw])->assertUnprocessable();
        $this->assertStringNotContainsString($raw, $this->allAuditJson());
        $this->assertSame(0, AuditEvent::where('action', 'settings.updated')->count());
    }

    public function test_price_endpoints_audit_cents_without_repricing_existing_orders(): void
    {
        $order = PlayerPlusOrder::create([
            'player_id' => Player::factory()->create()->id, 'user_id' => $this->admin->id,
            'request_token' => (string) Str::uuid(), 'price_cents' => 49900, 'expires_at' => now()->addMinutes(5),
        ]);
        $this->actingAs($this->admin)->postJson(route('settings.player-plus-price'), ['player_plus_annual_price' => '701.25'])->assertOk();
        $this->actingAs($this->admin)->postJson(route('settings.player-plus-monthly'), ['player_plus_monthly_price' => '71.25', 'player_plus_monthly_enabled' => true])->assertOk();
        $this->assertSame('70125', SiteSetting::get(PlayerPlusService::PRICE_SETTING));
        $this->assertSame('7125', SiteSetting::get(PlayerPlusService::MONTHLY_PRICE_SETTING));
        $this->assertSame(49900, $order->fresh()->price_cents);
        $this->assertSame(2, AuditEvent::where('action', 'settings.updated')->count());
    }

    public function test_every_mutation_route_rejects_an_ordinary_user(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['settings.store', 'settings.store.single', 'settings.store.template', 'settings.store.content', 'settings.player-plus-price', 'settings.player-plus-monthly'] as $route) {
            $this->postJson(route($route), [])->assertForbidden();
        }
    }

    public function test_coming_soon_toggle_is_super_user_only_and_audited_independently_of_sales(): void
    {
        $key = \App\Services\Performance\PlayerRatingAccess::COMING_SOON_SETTING;
        $this->actingAs(User::factory()->create())->postJson(route('settings.store.single'), ['key' => $key, 'value' => '1'])->assertForbidden();
        $this->assertDatabaseMissing('site_settings', ['key' => $key]);
        $this->actingAs($this->admin)->postJson(route('settings.store.single'), ['key' => $key, 'value' => '1'])->assertOk();
        $this->assertSame('1', SiteSetting::get($key));
        $this->assertDatabaseMissing('site_settings', ['key' => \App\Services\Performance\PlayerRatingAccess::SETTING]);
        $event = AuditEvent::where('action', 'settings.updated')->sole();
        $this->assertSame('1', $event->after[$key]['value']);
        $this->assertSame($this->admin->id, $event->actor_id);
    }

    public function test_audit_failure_rolls_back_all_values_and_does_not_invalidate_cache(): void
    {
        SiteSetting::set('registration_open', '1', SiteSetting::GROUP_REGISTRATION);
        Cache::spy();
        $writer = Mockery::mock(AuditWriter::class);
        $writer->shouldReceive('record')->once()->andThrow(new RuntimeException('Synthetic audit failure'));
        try {
            (new SettingsMutationService($writer))->apply($this->admin, [
                'registration_open' => ['value' => '0', 'group' => SiteSetting::GROUP_REGISTRATION],
                'withdrawal_deadline_days' => ['value' => '21', 'group' => SiteSetting::GROUP_REGISTRATION],
            ]);
            $this->fail('Audit failure must reject the mutation.');
        } catch (RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        }
        $this->assertSame('1', DB::table('site_settings')->where('key', 'registration_open')->value('value'));
        $this->assertDatabaseMissing('site_settings', ['key' => 'withdrawal_deadline_days', 'value' => '21']);
        Cache::shouldNotHaveReceived('forget');
    }

    public function test_disabled_audit_is_fail_closed_and_cache_invalidation_runs_after_successful_commit(): void
    {
        SiteSetting::set('registration_open', '1', SiteSetting::GROUP_REGISTRATION);
        config(['audit.enabled' => false]);
        try {
            app(SettingsMutationService::class)->apply($this->admin, ['registration_open' => ['value' => '0', 'group' => SiteSetting::GROUP_REGISTRATION]]);
            $this->fail('Missing audit evidence must reject settings writes.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('audit record', $e->getMessage());
        }
        $this->assertSame('1', SiteSetting::get('registration_open'));
        config(['audit.enabled' => true]);
        Cache::shouldReceive('forget')->once()->with('site_setting.registration_open')->andReturnUsing(function () {
            // RefreshDatabase keeps one outer test transaction; the operation's own transaction has committed.
            $this->assertSame(1, DB::transactionLevel());
            $this->assertSame('0', DB::table('site_settings')->where('key', 'registration_open')->value('value'));
            return true;
        });
        app(SettingsMutationService::class)->apply($this->admin, ['registration_open' => ['value' => '0', 'group' => SiteSetting::GROUP_REGISTRATION]]);
    }

    public function test_service_rejects_unknown_keys_and_wrong_groups_and_preserves_null_get_semantics(): void
    {
        foreach ([['merchant_key' => ['value' => 'never-persist', 'group' => 'payfast']], ['registration_open' => ['value' => '0', 'group' => 'general']]] as $changes) {
            try {
                app(SettingsMutationService::class)->apply($this->admin, $changes);
                $this->fail('Invalid settings must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('site_settings', ['key' => 'merchant_key']);
            }
        }
        DB::table('site_settings')->insert(['key' => 'synthetic_null', 'value' => null, 'group' => 'general']);
        $this->assertNull(SiteSetting::get('synthetic_null', 'fallback'));
        $this->assertSame('fallback', SiteSetting::get('synthetic_missing', 'fallback'));
    }

    private function allAuditJson(): string
    {
        return json_encode(DB::table('audit_events')->get(), JSON_THROW_ON_ERROR);
    }

    private function bulkPayload(): array
    {
        $payload = ['payfast_fee_percentage' => '3.2', 'payfast_fee_flat' => '2.00', 'payfast_vat_rate' => '15'];
        foreach (array_keys(SiteSetting::PAYMENT_METHOD_LABELS) as $method) {
            $payload['payfast_fee_pct_'.$method] = '3.2';
        }
        return $payload;
    }
}
