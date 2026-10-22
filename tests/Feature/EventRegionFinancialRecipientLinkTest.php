<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\ConvenorPayoutProjection;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\{Event, EventConvenor, EventRegion, EventRegionFinancialRecipientLink, EventRegionManager, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventRegionFinancialRecipientLinkTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $actor;
    private User $manager;
    private EventConvenor $director;
    private EventRegion $scope;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        $this->manager = User::factory()->create(['name' => 'Separate regional account']);
        $this->event = Event::factory()->create();
        $this->director = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->actor->id, 'role' => 'hoof']);
        $region = TeamRegion::create(['region_name' => 'QA Overberg']);
        $id = DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $this->scope = EventRegion::findOrFail($id);
        EventRegionManager::create(['event_id' => $this->event->id, 'region_id' => $region->id, 'event_region_id' => $id, 'user_id' => $this->manager->id]);
    }

    private function input(array $changes = []): array
    {
        return array_replace(['event_region_id' => $this->scope->id, 'source_manager_user_id' => $this->manager->id,
            'target_convenor_id' => $this->director->id, 'target_user_id' => $this->actor->id,
            'confirmed_same_person' => 1, 'evidence' => 'The target director explicitly confirms these are the same person, for this event financial projection only.',
            'request_token' => (string) Str::uuid()], $changes);
    }

    private function record(array $input, bool $apply = true): array
    {
        return app(FinancialLedgerService::class)->recordRegionalFinancialRecipientLink($this->event, $this->actor, $input, $apply);
    }

    private function project(int $balance = -17604): array
    {
        $report = collect([['scope' => $this->scope->fresh(), 'balance_cents' => $balance, 'pending_cents' => 0,
            'entries' => collect(), 'refunds' => collect(), 'recording_available' => true, 'available_cents' => max(0, $balance),
            'payout_cents' => 27604, 'recovery_cents' => 0, 'offset_cents' => 0, 'shortfall_cents' => 17604]]);
        return app(ConvenorPayoutProjection::class)->build($this->event,
            collect([['convenor' => $this->director->fresh(), 'final_payout' => 25498.06]]), $report);
    }

    private function rejected(callable $call, string $field): void
    {
        try { $call(); $this->fail('Expected validation failure.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey($field, $exception->errors()); }
    }

    public function test_preview_apply_and_exact_retry_preserve_audit_and_all_money_and_access_records(): void
    {
        $tables = ['event_region_managers', 'event_convenors', 'event_payouts', 'regional_clothing_settlements', 'clothing_refunds', 'wallet_transactions', 'clothing_provider_refund_attempts'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $activityCount = DB::table('activity_log')->count();
        $input = $this->input();
        $this->assertTrue($this->record($input, false)['preview']);
        $this->assertDatabaseCount('event_region_financial_recipient_links', 0);
        $this->assertSame($activityCount, DB::table('activity_log')->count());
        $first = $this->record($input);
        $this->assertTrue($first['created']);
        $this->assertFalse($this->record($input)['created']);
        $this->assertDatabaseCount('event_region_financial_recipient_links', 1);
        $this->assertSame($this->manager->id, (int) $first['link']->source_manager_user_id);
        $this->assertSame($this->actor->id, (int) $first['link']->confirmed_by);
        $this->assertSame($input['evidence'], $first['link']->evidence);
        $this->assertSame(2532202, $this->project()['cards'][$this->director->id]['combined_cents']);
        $this->assertSame(2549806, $this->project(0)['cards'][$this->director->id]['combined_cents']);
        foreach ($before as $table => $snapshot) $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        $this->rejected(fn () => $this->record(array_replace($input, ['evidence' => 'A different confirmation explanation'])), 'request_token');
        $this->rejected(fn () => $this->record($this->input()), 'event_region_id');
        $this->rejected(fn () => $first['link']->update(['evidence' => 'Changed audit history']), 'recipient_link');
    }

    public function test_stale_source_assignment_disables_projection_and_exact_retry(): void
    {
        $input = $this->input();
        $this->record($input);
        EventRegionManager::where('event_region_id', $this->scope->id)->update(['user_id' => User::factory()->create()->id]);
        $this->assertSame(0, $this->project()['cards'][$this->director->id]['clothing_cents']);
        $this->assertCount(1, $this->project()['unmatched_regions']);
        $this->rejected(fn () => $this->record($input), 'source_manager_user_id');
    }

    public function test_expired_or_changed_target_disables_the_financial_link(): void
    {
        $this->record($this->input());
        $this->director->update(['expires_at' => now()->subDay()]);
        $this->assertSame(0, $this->project()['cards'][$this->director->id]['clothing_cents']);
        $this->director->update(['expires_at' => null, 'user_id' => User::factory()->create()->id]);
        $this->assertSame(0, $this->project()['cards'][$this->director->id]['clothing_cents']);
        $this->assertDatabaseCount('event_region_financial_recipient_links', 1);
    }

    public function test_cross_event_wrong_source_and_duplicate_physical_association_are_rejected(): void
    {
        $foreign = Event::factory()->create();
        $this->rejected(fn () => app(FinancialLedgerService::class)->recordRegionalFinancialRecipientLink($foreign, $this->actor, $this->input(), true), 'event_region_id');
        $this->rejected(fn () => $this->record($this->input(['source_manager_user_id' => $this->actor->id])), 'source_manager_user_id');
        $other = EventConvenor::create(['event_id' => $foreign->id, 'user_id' => $this->actor->id, 'role' => 'hoof']);
        $this->rejected(fn () => $this->record($this->input(['target_convenor_id' => $other->id])), 'target_convenor_id');
        DB::table('event_regions')->insert(['event_id' => $this->event->id, 'region_id' => $this->scope->region_id, 'ordering' => 2]);
        $this->rejected(fn () => $this->record($this->input()), 'event_region_id');
        $this->assertDatabaseCount('event_region_financial_recipient_links', 0);
    }

    public function test_source_already_financial_director_cannot_be_reassigned_to_another_director(): void
    {
        EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->manager->id, 'role' => 'hulp']);
        $this->rejected(fn () => $this->record($this->input()), 'source_manager_user_id');
        $this->assertDatabaseCount('event_region_financial_recipient_links', 0);
    }

    public function test_revoked_super_role_is_not_accepted_from_a_preloaded_actor(): void
    {
        $this->assertTrue($this->actor->hasRole('super-user'));
        User::findOrFail($this->actor->id)->removeRole('super-user');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->record($this->input());
    }

    public function test_another_super_user_cannot_confirm_someone_elses_identity(): void
    {
        $other = User::factory()->create()->assignRole('super-user');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(FinancialLedgerService::class)->recordRegionalFinancialRecipientLink($this->event, $other, $this->input(), true);
    }

    public function test_production_environment_is_blocked_even_with_a_super_user(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->record($this->input());
    }
}
