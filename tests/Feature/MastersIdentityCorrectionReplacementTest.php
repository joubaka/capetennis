<?php

namespace Tests\Feature;

use App\Jobs\SendMastersInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\SeriesRanking;
use App\Models\Series;
use App\Models\User;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersIdentityCorrectionReplacementTest extends TestCase
{
    use RefreshDatabase {
        refreshTestDatabase as private refreshDatabaseUsingTrait;
    }

    private User $actor;

    private Player $target;

    private MastersInvitation $vacancy;

    private string $sqlitePath;

    protected function refreshTestDatabase(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'ct-masters-');
        if ($database === false) {
            throw new \RuntimeException('Unable to create the isolated Masters test database.');
        }
        $this->sqlitePath = $database;
        RefreshDatabaseState::$migrated = false;
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $database);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->refreshDatabaseUsingTrait();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->sqlitePath) && is_file($this->sqlitePath)) {
            @unlink($this->sqlitePath);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        $targetUser = User::factory()->create(['id' => 4025, 'email' => 'dirkie@example.test']);
        User::factory()->create(['id' => 2340]);

        Player::query()->forceCreate(['id' => 2439, 'name' => 'Dirk', 'surname' => 'Coetzee', 'dateOfBirth' => '2010-03-25', 'userId' => 2340]);
        $this->target = Player::query()->forceCreate(['id' => 5332, 'name' => 'Dirkie', 'surname' => 'Coetzee', 'dateOfBirth' => '2017-09-09', 'userId' => $targetUser->id]);

        Series::query()->forceCreate([
            'id' => 18,
            'name' => 'Wilson Series 2026',
            'year' => 2026,
            'minimum_events_for_team_selection' => 1,
        ]);
        $event = Event::factory()->create(['id' => 254, 'name' => 'Wilson Masters 2026']);
        Event::factory()->create(['id' => 230, 'name' => 'Cavaliers Junior Wilson Paarl Tournament 2026', 'series_id' => 18]);
        Event::factory()->create(['id' => 237, 'name' => 'Cavaliers Junior Strand Tournament 2026', 'series_id' => 18]);
        $category = Category::query()->forceCreate(['id' => 131, 'name' => 'Boys U/9']);
        $mastersCategory = Category::query()->forceCreate(['id' => 224, 'name' => 'Boys U/9 Masters']);
        CategoryEvent::query()->forceCreate(['id' => 2182, 'event_id' => $event->id, 'category_id' => $mastersCategory->id, 'entry_fee' => 285, 'ordering' => 938]);
        CategoryEvent::query()->forceCreate(['id' => 1861, 'event_id' => 230, 'category_id' => $category->id, 'entry_fee' => 285]);
        CategoryEvent::query()->forceCreate(['id' => 2011, 'event_id' => 237, 'category_id' => $category->id, 'entry_fee' => 285]);
        MastersInvitationBatch::query()->create([
            'id' => 4, 'event_id' => 254, 'series_id' => 18, 'ranking_run_id' => 'published-run',
            'created_by' => $this->actor->id, 'top_x' => 8, 'status' => 'sent',
            'public_list_published' => true, 'registration_open' => true,
            'response_deadline' => now()->addDay(), 'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
        ]);
        $this->vacancy = MastersInvitation::query()->create([
            'id' => 807, 'batch_id' => 4, 'event_id' => 254, 'category_event_id' => 2182,
            'ranking_list_id' => 938, 'ranking_category_id' => 131, 'player_id' => 2439,
            'ranking_position' => 8, 'queue_position' => 8, 'total_points' => 80,
            'status' => MastersInvitation::DECLINED, 'declined_at' => now()->subDay(),
            'decline_reason' => 'Recorded against the wrong identity',
            'snapshot_json' => ['player_name' => 'Dirk Coetzee', 'rank_position' => 8, 'total_points' => 80],
        ]);
        foreach ([18445, 20410] as $registrationId) {
            Registration::query()->forceCreate(['id' => $registrationId]);
            DB::table('player_registrations')->insert([
                'registration_id' => $registrationId,
                'player_id' => 5332,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('category_event_registrations')->insert([
            ['category_event_id' => 1861, 'registration_id' => 18445, 'user_id' => 4025, 'status' => 'active', 'payment_status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['category_event_id' => 2011, 'registration_id' => 20410, 'user_id' => 4025, 'status' => 'active', 'payment_status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        SeriesRanking::query()->create([
            'series_id' => 18,
            'ranking_list_id' => 938,
            'category_id' => 131,
            'player_id' => 5332,
            'rank_position' => 8,
            'total_points' => 80,
            'meta_json' => ['events_played' => 2],
            'status' => 'published',
            'run_id' => 'dirkie-published-run',
            'published_by' => $this->actor->id,
            'published_at' => now(),
        ]);
    }

    public function test_it_creates_a_distinct_replacement_preserves_vacancy_and_queues_once_after_commit(): void
    {
        $vacancyBefore = MastersInvitation::query()->findOrFail(807)->getRawOriginal();

        DB::beginTransaction();
        $replacement = $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);

        BulkEmailLog::query()->where('related_id', $replacement->id)->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $replayed = $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);

        $this->assertSame($replacement->id, $replayed->id);
        $this->assertNotSame(807, $replacement->id);
        $this->assertSame(5332, (int) $replacement->player_id);
        $this->assertSame(807, (int) $replacement->promoted_from_id);
        $this->assertSame(938, (int) $replacement->ranking_list_id);
        $this->assertSame(131, (int) $replacement->ranking_category_id);
        $this->assertSame(8, (int) $replacement->ranking_position);
        $this->assertSame(8, (int) $replacement->queue_position);
        $this->assertSame(80, (int) $replacement->total_points);
        $this->assertSame(MastersInvitation::INVITED, $replacement->status);
        $this->assertNull($replacement->registration_id);
        $this->assertNull($replacement->order_id);
        $this->assertNull($replacement->declined_at);
        $this->assertNotNull($replacement->invited_at);
        $this->assertNotNull($replacement->replacement_sent_at);
        $this->assertSame($vacancyBefore, MastersInvitation::query()->findOrFail(807)->getRawOriginal());
        $this->assertSame(1, BulkEmailLog::query()->where('related_id', $replacement->id)->count());
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => MastersInvitation::class,
            'subject_id' => $replacement->id,
            'causer_id' => $this->actor->id,
            'description' => 'Masters replacement invitation created after player identity correction',
        ]);
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('registration_order_items', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_it_accepts_two_legacy_counting_legs_and_records_the_derived_evidence(): void
    {
        $this->updateRankingMeta([
            'counting_legs' => [
                ['category_event_id' => 1861, 'position' => 4, 'points' => 50],
                ['category_event_id' => 2011, 'position' => 7, 'points' => 30],
            ],
        ]);

        $replacement = $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);

        $this->assertSame(2, $replacement->snapshot_json['events_played']);
        $this->assertSame('counting_legs', $replacement->snapshot_json['events_played_evidence_source']);
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);
    }

    public function test_it_rejects_conflicting_explicit_and_counting_leg_evidence(): void
    {
        $this->updateRankingMeta([
            'events_played' => 2,
            'counting_legs' => [['category_event_id' => 1861]],
        ]);

        $this->assertRankingEvidenceRejected();
    }

    public function test_it_rejects_one_counting_leg(): void
    {
        $this->updateRankingMeta(['counting_legs' => [['category_event_id' => 1861]]]);

        $this->assertRankingEvidenceRejected();
    }

    public function test_it_rejects_malformed_or_duplicate_counting_leg_evidence(): void
    {
        foreach ([
            ['counting_legs' => [['position' => 4], ['category_event_id' => 2011]]],
            ['counting_legs' => [['category_event_id' => 1861], ['category_event_id' => 1861]]],
        ] as $meta) {
            $this->updateRankingMeta($meta);
            $this->assertRankingEvidenceRejected();
        }
    }

    public function test_it_rejects_wrong_or_cross_series_counting_leg_evidence(): void
    {
        $this->updateRankingMeta([
            'counting_legs' => [
                ['category_event_id' => 1861],
                ['category_event_id' => 9999],
            ],
        ]);
        $this->assertRankingEvidenceRejected();

        Event::query()->whereKey(237)->update(['series_id' => null]);
        $this->updateRankingMeta([
            'counting_legs' => [
                ['category_event_id' => 1861],
                ['category_event_id' => 2011],
            ],
        ]);
        $this->assertRankingEvidenceRejected();
    }

    public function test_it_requires_a_super_user_without_creating_or_queueing_anything(): void
    {
        $ordinary = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $ordinary);
        } finally {
            $this->assertSame(1, MastersInvitation::query()->count());
            $this->assertSame(0, BulkEmailLog::query()->count());
            Queue::assertNothingPushed();
        }
    }

    public function test_it_rolls_back_when_corrected_registrations_or_deadline_drift(): void
    {
        DB::table('player_registrations')->where('registration_id', 20410)->update(['player_id' => 2439]);

        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected corrected registration drift to stop replacement creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registrations', $exception->errors());
        }

        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();

        DB::table('player_registrations')->where('registration_id', 20410)->update(['player_id' => 5332]);
        MastersInvitationBatch::query()->whereKey(4)->update(['replacement_payment_deadline' => now()->subMinute()]);
        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected deadline drift to stop replacement creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('batch', $exception->errors());
        }
        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_a_mismatched_existing_target_invitation_without_mail(): void
    {
        MastersInvitation::query()->create([
            'batch_id' => 4, 'event_id' => 254, 'category_event_id' => 2182,
            'ranking_list_id' => 938, 'ranking_category_id' => 131, 'player_id' => 5332,
            'ranking_position' => 9, 'queue_position' => 9, 'total_points' => 75,
            'status' => MastersInvitation::RESERVE,
        ]);

        $this->expectException(ValidationException::class);
        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
        } finally {
            $this->assertSame(2, MastersInvitation::query()->count());
            $this->assertSame(0, BulkEmailLog::query()->count());
            Queue::assertNothingPushed();
        }
    }

    public function test_it_rejects_batch_state_drift_without_mail(): void
    {
        MastersInvitationBatch::query()->whereKey(4)->update(['registration_open' => false]);

        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected batch state drift to stop replacement creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('batch', $exception->errors());
        }

        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_wrong_masters_event_category_without_conflating_the_ranking_category(): void
    {
        CategoryEvent::query()->whereKey(2182)->update(['category_id' => 131]);

        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected the wrong Masters event category to stop replacement creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category', $exception->errors());
        }

        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_it_rejects_existing_masters_financial_rows_without_mutating_them(): void
    {
        $order = RegistrationOrder::query()->create([
            'user_id' => 4025,
            'pay_status' => false,
            'payfast_paid' => false,
            'total_fee' => 285,
            'status' => 'pending',
        ]);
        $item = RegistrationOrderItems::query()->forceCreate([
            'order_id' => $order->id,
            'registration_id' => 18445,
            'player_id' => 5332,
            'category_event_id' => 2182,
            'user_id' => 4025,
            'item_price' => 285,
        ]);
        $orderBefore = $order->fresh()->getRawOriginal();
        $itemBefore = $item->fresh()->getRawOriginal();
        $transactionId = DB::table('transactions_pf')->insertGetId([
            'pf_payment_id' => 'order-linked-only',
            'event_id' => 254,
            'category_event_id' => 2182,
            'player_id' => 9999,
            'custom_int2' => 9999,
            'custom_int5' => $order->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $transactionBefore = (array) DB::table('transactions_pf')->where('id', $transactionId)->sole();

        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected Masters financial evidence to stop replacement creation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
            $this->assertStringContainsString('PayFast', $exception->errors()['payment'][0]);
        }

        $this->assertSame($orderBefore, $order->fresh()->getRawOriginal());
        $this->assertSame($itemBefore, $item->fresh()->getRawOriginal());
        $this->assertSame($transactionBefore, (array) DB::table('transactions_pf')->where('id', $transactionId)->sole());
        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_replay_recovers_a_missing_or_failed_email_log_using_the_same_invitation(): void
    {
        $replacement = $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
        BulkEmailLog::query()->where('related_id', $replacement->id)->delete();
        Queue::fake();

        $missingRecovered = $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
        $this->assertSame($replacement->id, $missingRecovered->id);
        $log = BulkEmailLog::query()->where('related_id', $replacement->id)->sole();
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);

        $log->update(['status' => 'failed', 'failed_at' => now()]);
        Queue::fake();
        $failedRecovered = $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
        $this->assertSame($replacement->id, $failedRecovered->id);
        $this->assertSame($log->id, BulkEmailLog::query()->where('related_id', $replacement->id)->sole()->id);
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);
    }

    public function test_it_requires_the_exact_published_target_ranking_and_pinned_vacancy(): void
    {
        SeriesRanking::query()->where('player_id', 5332)->delete();
        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected the missing published target ranking to block the invitation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ranking', $exception->errors());
        }
        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());

        SeriesRanking::query()->create([
            'series_id' => 18, 'ranking_list_id' => 938, 'category_id' => 131,
            'player_id' => 5332, 'rank_position' => 8, 'total_points' => 80,
            'meta_json' => ['events_played' => 2], 'status' => 'published',
        ]);
        MastersInvitation::query()->whereKey(807)->update(['queue_position' => 9]);
        $this->expectException(ValidationException::class);
        $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
    }

    public function test_it_rejects_legacy_payfast_player_evidence_without_mail(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'legacy-only',
            'event_id' => 254,
            'category_event_id' => 2182,
            'player_id' => null,
            'custom_int2' => 5332,
            'custom_int5' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected legacy PayFast player evidence to block the invitation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_guarded_command_requires_confirmation_and_super_user_actor(): void
    {
        $this->artisan('masters:create-dirkie-identity-replacement', [
            '--actor' => $this->actor->id,
            '--confirm' => 'WRONG',
        ])->assertFailed();

        $ordinary = User::factory()->create();
        $this->artisan('masters:create-dirkie-identity-replacement', [
            '--actor' => $ordinary->id,
            '--confirm' => 'DIRKIE-5332-WILSON-U9',
        ])->assertFailed();

        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_guarded_command_creates_the_replacement_and_queues_only_player_mail(): void
    {
        $this->artisan('masters:create-dirkie-identity-replacement', [
            '--actor' => $this->actor->id,
            '--confirm' => 'DIRKIE-5332-WILSON-U9',
        ])->assertSuccessful();

        $replacement = MastersInvitation::query()->where('player_id', 5332)->sole();
        $this->assertSame(807, (int) $replacement->promoted_from_id);
        $this->assertSame(1, BulkEmailLog::query()->where('related_id', $replacement->id)->count());
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);
    }

    private function service(): MastersInvitationService
    {
        return app(MastersInvitationService::class);
    }

    private function updateRankingMeta(array $meta): void
    {
        SeriesRanking::query()->where('player_id', 5332)->update(['meta_json' => json_encode($meta, JSON_THROW_ON_ERROR)]);
    }

    private function assertRankingEvidenceRejected(): void
    {
        try {
            $this->service()->createIdentityCorrectionReplacement($this->vacancy, $this->target, $this->actor);
            $this->fail('Expected invalid ranking event evidence to block the invitation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ranking', $exception->errors());
        }

        $this->assertSame(1, MastersInvitation::query()->count());
        $this->assertSame(0, BulkEmailLog::query()->count());
        Queue::assertNothingPushed();
    }
}
