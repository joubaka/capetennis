<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Services\RankingReviewCirculationService;
use App\Jobs\SendBulkEmailJob;
use App\Models\BulkEmailLog;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\CategoryResult;
use App\Models\Event;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\RankingReviewCampaign;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankingReviewCirculationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Series $series;
    private RankingList $list;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->series = Series::factory()->create(['ranking_review_default_hours' => 24]);
        $event = Event::factory()->create(['series_id' => $this->series->id]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $this->admin->id]);
        $this->category = Category::factory()->create(['name' => 'U/13 Boys']);
        $this->list = RankingList::factory()->create([
            'series_id' => $this->series->id,
            'category_id' => $this->category->id,
        ]);
    }

    public function test_preview_uses_only_the_reviewed_run_and_reports_shared_and_missing_emails(): void
    {
        $this->rankedPlayer('One', 'family@example.test', 1);
        $this->rankedPlayer('Two', 'FAMILY@example.test', 2);
        $this->rankedPlayer('Missing', null, 3);

        $preview = app(RankingReviewCirculationService::class)->preview($this->series);

        $this->assertSame('review-run', $preview['run_id']);
        $this->assertSame(3, $preview['audience']['player_count']);
        $this->assertSame(1, $preview['audience']['recipient_count']);
        $this->assertSame(1, $preview['audience']['shared_email_count']);
        $this->assertSame(1, $preview['audience']['missing_email_count']);
        $this->assertSame('Missing Player', $preview['audience']['missing'][0]['name']);
    }

    public function test_send_persists_immutable_campaign_audience_and_email_log(): void
    {
        $this->rankedPlayer('One', 'one@example.test', 1);
        $this->rankedPlayer('Missing', null, 2);
        $uuid = (string) Str::uuid();

        $campaign = app(RankingReviewCirculationService::class)->send(
            $this->series,
            $this->admin,
            $uuid,
            'Please check rankings',
            'Review your ranking and reply before the cutoff.',
            'rankings@example.test',
            now()->addDay(),
        );

        $this->assertSame(2, $campaign->player_count);
        $this->assertSame(1, $campaign->recipient_count);
        $this->assertSame(1, $campaign->missing_email_count);
        $this->assertCount(2, $campaign->recipients);
        $this->assertDatabaseHas('bulk_email_logs', [
            'mail_type' => 'ranking_review',
            'related_id' => $campaign->id,
            'recipient_email' => 'one@example.test',
            'status' => 'queued',
        ]);

        $again = app(RankingReviewCirculationService::class)->send(
            $this->series,
            $this->admin,
            $uuid,
            'Changed by a duplicate click',
            'Changed',
            'changed@example.test',
            now()->addDays(2),
        );
        $this->assertSame($campaign->id, $again->id);
        $this->assertSame('Please check rankings', $again->subject);
        $this->assertSame(1, RankingReviewCampaign::count());
    }

    public function test_active_circulation_blocks_early_publication_then_finalizes_the_same_run_after_cutoff(): void
    {
        Queue::fake();
        $this->rankedPlayer('One', 'one@example.test', 1);
        $campaign = app(RankingReviewCirculationService::class)->send(
            $this->series,
            $this->admin,
            (string) Str::uuid(),
            'Please check rankings',
            'Review your ranking.',
            'rankings@example.test',
            now()->addHour(),
        );
        Queue::assertPushed(SendBulkEmailJob::class, 1);

        $this->actingAs($this->admin)
            ->postJson(route('ranking.series.ranking.publish', $this->series))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cutoff');
        $this->assertDatabaseHas('series_rankings', ['run_id' => 'review-run', 'status' => 'reviewed']);

        $campaign->update(['cutoff_at' => now()->subMinute()]);
        BulkEmailLog::where('related_type', RankingReviewCampaign::class)
            ->where('related_id', $campaign->id)
            ->update(['status' => 'sent']);
        $this->postJson(route('ranking.series.ranking.publish', $this->series))
            ->assertOk()
            ->assertJsonPath('message', 'Participant review closed. Rankings finalized and published. No ranking emails were sent.');

        $this->assertDatabaseHas('series_rankings', ['run_id' => 'review-run', 'status' => 'published']);
        $this->assertDatabaseHas('ranking_review_campaigns', ['id' => $campaign->id, 'status' => 'finalized']);
        Queue::assertPushed(SendBulkEmailJob::class, 1);
    }

    public function test_signed_provisional_page_is_run_scoped_and_superseded_links_close(): void
    {
        $this->rankedPlayer('One', 'one@example.test', 1);
        $campaign = app(RankingReviewCirculationService::class)->send(
            $this->series,
            $this->admin,
            (string) Str::uuid(),
            'Please check rankings',
            'Review your ranking.',
            'rankings@example.test',
            now()->addHour(),
        );
        $url = URL::signedRoute('ranking.review.public', ['campaign' => $campaign->uuid]);

        $this->get($url)
            ->assertOk()
            ->assertSee($this->series->name)
            ->assertSee('One Player')
            ->assertSee('Participant review open');

        $campaign->update(['status' => 'superseded']);
        $this->get($url)->assertStatus(410);
    }

    public function test_signed_provisional_page_shows_each_event_score_finish_and_counting_status(): void
    {
        Queue::fake();
        $player = $this->rankedPlayer('Detail', 'detail@example.test', 1);
        $events = Event::query()->where('series_id', $this->series->id)->get();
        $countedEvent = $events->first();
        $countedEvent->update(['name' => 'Overberg Leg 1', 'results_published' => true]);
        $droppedEvent = Event::factory()->create([
            'series_id' => $this->series->id,
            'name' => 'Overberg Leg 2',
            'results_published' => true,
        ]);
        $countedCategoryEvent = CategoryEvent::factory()->create(['event_id' => $countedEvent->id, 'category_id' => $this->category->id]);
        $droppedCategoryEvent = CategoryEvent::factory()->create(['event_id' => $droppedEvent->id, 'category_id' => $this->category->id]);
        $registration = \App\Models\Registration::factory()->create();
        $registration->players()->attach($player->id);
        CategoryResult::create([
            'event_id' => $countedEvent->id,
            'category_id' => $this->category->id,
            'registration_id' => $registration->id,
            'position' => 1,
        ]);

        SeriesRanking::where('player_id', $player->id)->update([
            'total_points' => 900,
            'meta_json' => [
                'counting_legs' => [[
                    'category_event_id' => $countedCategoryEvent->id,
                    'position' => 2,
                    'points' => 900,
                ]],
                'dropped_legs' => [[
                    'category_event_id' => $droppedCategoryEvent->id,
                    'position' => 4,
                    'points' => 500,
                ]],
            ],
        ]);

        $campaign = app(RankingReviewCirculationService::class)->send(
            $this->series,
            $this->admin,
            (string) Str::uuid(),
            'Please check rankings',
            'Review your ranking.',
            'rankings@example.test',
            now()->addHour(),
        );

        $this->get(URL::signedRoute('ranking.review.public', ['campaign' => $campaign->uuid]))
            ->assertOk()
            ->assertSee('ranking-review-list', false)
            ->assertSee('Click to open')
            ->assertSee('Filter players in this ranking list')
            ->assertSee('Scores per event')
            ->assertSee('Overberg Leg 1')
            ->assertSee('<span class="badge bg-label-primary mt-1">Leg 1</span>', false)
            ->assertSee('900 pts')
            ->assertSee('Finished #1')
            ->assertSee('Ranking points position #2')
            ->assertSee('Overberg Leg 2')
            ->assertSee('500 pts')
            ->assertSee('Not counted');
    }

    public function test_other_users_cannot_preview_or_send_a_series_circulation(): void
    {
        $this->rankedPlayer('One', 'one@example.test', 1);
        $ordinary = User::factory()->create();

        $this->actingAs($ordinary)
            ->getJson(route('ranking.series.review-circulation.preview', $this->series))
            ->assertForbidden();
        $this->postJson(route('ranking.series.review-circulation.send', $this->series), [
            'uuid' => (string) Str::uuid(),
            'subject' => 'Subject',
            'message' => 'Message',
            'reply_to' => 'reply@example.test',
            'cutoff_at' => now()->addDay()->toIso8601String(),
        ])->assertForbidden();
    }

    private function rankedPlayer(string $firstName, ?string $email, int $position): Player
    {
        $player = Player::factory()->create([
            'name' => $firstName,
            'surname' => 'Player',
            'email' => $email,
            'userId' => null,
        ]);
        SeriesRanking::create([
            'series_id' => $this->series->id,
            'ranking_list_id' => $this->list->id,
            'category_id' => $this->category->id,
            'player_id' => $player->id,
            'rank_position' => $position,
            'total_points' => 1000 - ($position * 100),
            'status' => 'reviewed',
            'run_id' => 'review-run',
            'meta_json' => [],
        ]);

        return $player;
    }
}
