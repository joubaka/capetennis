<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Artisan, DB, Schema};
use Tests\TestCase;

class PlayerPlusReadinessReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->mock(\App\Services\Performance\PlayerPlusDerivedRefreshState::class)->shouldReceive('read')->byDefault()->andReturn(null);
    }

    public function test_aggregate_readiness_is_read_only_and_excludes_private_values(): void
    {
        Schema::create('site_settings', function (Blueprint $t) { $t->string('key'); $t->string('value'); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('email'); });
        Schema::create('players', function (Blueprint $t) { $t->id(); $t->integer('userId')->nullable(); });
        Schema::create('user_players', function (Blueprint $t) { $t->integer('user_id'); $t->integer('player_id'); });
        Schema::create('player_plus_orders', function (Blueprint $t) {
            $t->id(); $t->integer('player_id'); $t->string('status'); $t->boolean('pay_status'); $t->boolean('payfast_paid'); $t->timestamp('activated_at')->nullable(); $t->string('reconciliation_reason')->nullable();
        });
        Schema::create('player_plus_memberships', function (Blueprint $t) { $t->id(); $t->integer('player_id'); $t->integer('order_id'); $t->timestamp('starts_at'); $t->timestamp('ends_at'); });
        Schema::create('player_plus_subscriptions', function (Blueprint $t) { $t->id(); $t->string('status'); $t->string('provider_token'); $t->timestamp('cancellation_requested_at')->nullable(); $t->timestamp('cancelled_at')->nullable(); });
        Schema::create('player_ability_snapshots', function (Blueprint $t) { $t->id(); $t->string('snapshot_key'); $t->integer('model_version'); $t->string('policy_hash'); });
        Schema::create('audit_events', function (Blueprint $t) { $t->id(); $t->string('action'); $t->string('subject_type'); $t->string('subject_id'); $t->json('metadata'); });
        DB::table('site_settings')->insert([
            ['key' => 'player_rating_membership_enabled', 'value' => '1'], ['key' => 'player_plus_monthly_enabled', 'value' => '1'],
            ['key' => 'player_plus_annual_price_cents', 'value' => '75000'], ['key' => 'player_plus_monthly_price_cents', 'value' => '7500'],
            ['key' => 'smtp_password', 'value' => 'SECRET_SMTP_SENTINEL'],
        ]);
        DB::table('users')->insert([['id' => 1, 'email' => 'PRIVATE_EMAIL_SENTINEL@example.test'], ['id' => 2, 'email' => 'second@example.test']]);
        DB::table('players')->insert(['id' => 7, 'userId' => 1]);
        DB::table('user_players')->insert(['user_id' => 2, 'player_id' => 7]);
        DB::table('player_plus_orders')->insert(['id' => 9, 'player_id' => 7, 'status' => 'completed', 'pay_status' => true, 'payfast_paid' => true, 'activated_at' => now(), 'reconciliation_reason' => 'PRIVATE_RECONCILIATION_SENTINEL']);
        DB::table('player_plus_memberships')->insert(['player_id' => 7, 'order_id' => 9, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        DB::table('player_plus_subscriptions')->insert(['status' => 'cancel_pending', 'provider_token' => 'SECRET_PROVIDER_SENTINEL', 'cancellation_requested_at' => now()]);
        DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'model_version' => PlayerSharedAbilityService::VERSION, 'policy_hash' => 'current-policy']);
        DB::table('audit_events')->insert(['action' => 'player.account-linked', 'subject_type' => Player::class, 'subject_id' => '7', 'metadata' => json_encode(['target_user_id' => 2])]);
        $snapshot = $this->mock(PlayerAbilitySnapshotStore::class);
        $snapshot->shouldReceive('current')->once()->andReturn(['reason' => null, 'snapshot_as_of' => '2026-11-15', 'snapshot_stale' => false, 'all_age' => ['ratings' => [7 => ['comparable' => true, 'confidence_label' => 'Medium']]]]);
        $snapshot->shouldReceive('policyHash')->once()->andReturn('current-policy');
        $snapshot->shouldReceive('published')->once()->andReturn(true);
        $snapshot->shouldNotReceive('replace', 'manifest');
        $ability = $this->mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->once()->andReturn('PRIVATE_FINGERPRINT_SENTINEL');
        $ability->shouldNotReceive('calculateSnapshot', 'historySnapshot', 'validationSnapshot');
        $this->mock(PlayerRatingHistoryStore::class)->shouldReceive('read')->once()->andReturn(['as_of' => '2026-11-15', 'source_fingerprint' => 'PRIVATE_FINGERPRINT_SENTINEL', 'publication_manifest' => [], 'months' => [
            ['cohorts' => [AllAgeRatingModel::COHORT => [7 => ['group' => 'same']]]],
            ['cohorts' => [AllAgeRatingModel::COHORT => [7 => ['group' => 'same']]]],
            ['cohorts' => [AllAgeRatingModel::COHORT => [7 => ['group' => 'changed']]]],
        ]]);
        $before = [];
        foreach (['site_settings', 'user_players', 'player_plus_orders', 'player_plus_memberships', 'player_plus_subscriptions', 'player_ability_snapshots', 'audit_events'] as $table) $before[$table] = DB::table($table)->get()->toJson();
        $writes = [];
        DB::listen(function ($query) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query->sql)) $writes[] = $query->sql; });
        $this->assertSame(0, Artisan::call('player-plus:readiness'));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($report['read_only']);
        $this->assertSame(75000, $report['offers']['annual_price']['effective_cents']);
        $this->assertTrue($report['offers']['sales_enabled']);
        $this->assertFalse($report['offers']['monthly_enabled'], 'Missing subscription charge schema must keep monthly service unavailable');
        $this->assertTrue($report['saved_ratings']['policy_current']);
        $this->assertSame(1, $report['saved_ratings']['overall_comparable_players']);
        $this->assertSame(1, $report['saved_ratings']['overall_confidence_counts']['Medium']);
        $this->assertSame(0.5, $report['saved_history']['overall_same_group_ratio']);
        $this->assertSame(1, $report['billing_review']['orders_needing_reconciliation']);
        $this->assertSame(1, $report['billing_review']['subscription_cancellations_pending']);
        $this->assertSame(1, $report['relationships']['active_plus_players']);
        $this->assertSame(2, $report['relationships']['active_plus_relationship_pairs']);
        $this->assertSame(1, $report['relationships']['active_plus_pairs_with_link_audit']);
        $this->assertSame(1, $report['relationships']['active_plus_pairs_with_unknown_link_audit']);
        $this->assertSame([], $writes);
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson());
        foreach (['SECRET_SMTP_SENTINEL', 'SECRET_PROVIDER_SENTINEL', 'PRIVATE_EMAIL_SENTINEL', 'PRIVATE_RECONCILIATION_SENTINEL', 'PRIVATE_FINGERPRINT_SENTINEL', 'target_user_id', 'player_id', 'user_id'] as $private) $this->assertStringNotContainsString($private, $output);
    }

    public function test_missing_optional_schema_is_reported_without_errors_or_mutations(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn(['reason' => 'Saved ratings are unavailable.']);
        $this->mock(PlayerRatingHistoryStore::class)->shouldReceive('read')->once()->andReturn(null);
        $this->assertSame(0, Artisan::call('player-plus:readiness'));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        foreach (['offers', 'saved_ratings', 'saved_history', 'billing_review', 'relationships'] as $section) $this->assertFalse($report[$section]['available']);
        $this->assertFalse($report['schema']['tables']['player_plus_orders']);
    }

    public function test_invalid_price_values_use_safe_checkout_defaults_without_disclosing_raw_values(): void
    {
        Schema::create('site_settings', function (Blueprint $t) { $t->string('key'); $t->string('value'); });
        DB::table('site_settings')->insert([
            ['key' => 'player_plus_annual_price_cents', 'value' => 'SECRET_INVALID_PRICE_SENTINEL'],
            ['key' => 'player_plus_monthly_price_cents', 'value' => '-1'],
        ]);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn(['reason' => 'Saved ratings are unavailable.']);
        $this->mock(PlayerRatingHistoryStore::class)->shouldReceive('read')->once()->andReturn(null);
        $this->assertSame(0, Artisan::call('player-plus:readiness'));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(69900, $report['offers']['annual_price']['effective_cents']);
        $this->assertSame(6900, $report['offers']['monthly_price']['effective_cents']);
        $this->assertFalse($report['offers']['annual_price']['configured_value_valid']);
        $this->assertNull($report['offers']['annual_price']['configured_cents']);
        $this->assertStringNotContainsString('SECRET_INVALID_PRICE_SENTINEL', $output);
    }

    public function test_saved_history_reports_date_and_source_staleness_without_withholding_valid_publication(): void
    {
        $today = now('Africa/Johannesburg')->toDateString();
        $yesterday = now('Africa/Johannesburg')->subDay()->toDateString();
        $snapshot = $this->mock(PlayerAbilitySnapshotStore::class);
        $snapshot->shouldReceive('current')->times(3)->andReturn(['reason' => 'Saved ratings are unavailable.']);
        $snapshot->shouldReceive('published')->times(3)->andReturn(true);
        $ability = $this->mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->times(3)->andReturn('current-source');
        $ability->shouldNotReceive('calculateSnapshot', 'historySnapshot', 'validationSnapshot');
        $saved = fn ($date, $source) => ['as_of' => $date, 'source_fingerprint' => $source, 'publication_manifest' => [], 'months' => []];
        $this->mock(PlayerRatingHistoryStore::class)->shouldReceive('read')->times(3)->andReturn(
            $saved($today, 'current-source'), $saved($yesterday, 'current-source'), $saved($today, 'earlier-source')
        );
        foreach ([[false, true], [true, true], [true, false]] as [$stale, $sourceCurrent]) {
            $this->assertSame(0, Artisan::call('player-plus:readiness'));
            $output = Artisan::output();
            $history = json_decode($output, true, flags: JSON_THROW_ON_ERROR)['saved_history'];
            $this->assertTrue($history['available']);
            $this->assertTrue($history['publication_context_current']);
            $this->assertSame($stale, $history['stale']);
            $this->assertSame($sourceCurrent, $history['source_fingerprint_current']);
            $this->assertStringNotContainsString('current-source', $output);
            $this->assertStringNotContainsString('earlier-source', $output);
        }
    }

    public function test_nightly_refresh_report_is_a_safe_read_only_aggregate_projection(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn(['reason' => 'Saved ratings are unavailable.']);
        $this->mock(PlayerRatingHistoryStore::class)->shouldReceive('read')->once()->andReturn(null);
        $this->mock(\App\Services\Performance\PlayerPlusDerivedRefreshState::class)->shouldReceive('read')->once()->andReturn([
            'status' => 'pending', 'stage' => 'events', 'cycle_date' => '2026-11-15', 'processed' => 7, 'progress_failures' => 1, 'event_failures' => 0,
            'ability_attempts' => 1, 'history_attempts' => 1, 'updated_at' => '2026-11-15T03:12:00+02:00', 'reason' => null,
            'cursor' => 123456, 'player_id' => 654321, 'context' => ['source_fingerprint' => 'PRIVATE_SOURCE_SENTINEL'], 'reference' => 'PRIVATE_REFERENCE_SENTINEL',
        ]);
        $this->assertSame(0, Artisan::call('player-plus:readiness')); $output = Artisan::output(); $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertTrue($report['nightly_refresh']['available']); $this->assertSame('pending', $report['nightly_refresh']['status']); $this->assertSame(7, $report['nightly_refresh']['processed']);
        foreach (['cursor', 'player_id', 'context', 'reference', 'PRIVATE_SOURCE_SENTINEL', 'PRIVATE_REFERENCE_SENTINEL', '123456', '654321'] as $private) $this->assertStringNotContainsString($private, json_encode($report['nightly_refresh']));
    }
}
