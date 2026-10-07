<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\Event;
use App\Models\TeamFixture;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use App\Models\Venue;
use App\Services\Performance\PendingBankRefundCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_badge_counts_once_across_views_and_refreshes_for_a_new_scope(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank', 'refund_status' => 'pending',
        ]);
        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'wallet', 'refund_status' => 'pending',
        ]);
        CategoryEventRegistration::factory()->create([
            'status' => 'active', 'refund_method' => 'bank', 'refund_status' => 'pending',
        ]);
        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank', 'refund_status' => 'refunded',
        ]);
        TeamPaymentOrder::create(['refund_method' => 'bank', 'refund_status' => 'pending']);
        TeamPaymentOrder::create(['refund_method' => 'wallet', 'refund_status' => 'pending']);
        TeamPaymentOrder::create(['refund_method' => 'bank', 'refund_status' => 'refunded']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (range(1, 10) as $index) {
            $view = View::make('frontend.event.partials.event-about');
            View::callComposer($view);
            $this->assertSame(2, $view->getData()['pendingBankRefundCount']);
        }
        $queries = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], 'count(*)') && str_contains($query['query'], 'refund_status'));
        $this->assertCount(2, $queries);
        DB::disableQueryLog();

        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank', 'refund_status' => 'pending',
        ]);
        $this->app->forgetScopedInstances();
        $this->assertSame(3, app(PendingBankRefundCount::class)->count());
    }

    public function test_refund_badge_does_not_query_or_share_counts_with_ordinary_users(): void
    {
        $this->actingAs(User::factory()->create());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $view = View::make('frontend.event.partials.event-about');
        View::callComposer($view);
        $this->assertArrayNotHasKey('pendingBankRefundCount', $view->getData());
        $this->assertEmpty(collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'refund_status')));
        DB::disableQueryLog();
    }

    public function test_team_venue_grouping_does_not_query_individual_schedule_or_reveal_private_venue(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => false]);
        $venue = Venue::forceCreate(['name' => 'Private working venue']);
        foreach (range(1, 5) as $index) {
            TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => $index,
                'venue_id' => $venue->id, 'scheduled_at' => '2026-10-10 08:00:00']);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/events/'.$event->id)->assertOk();
        $groups = $response->viewData('fixturesPerVenueGrouped');
        $this->assertSame(['Unassigned'], $groups->keys()->all());
        $this->assertCount(5, $groups->get('Unassigned'));
        $lazySchedules = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], '`order_of_plays`.`fixture_id` = ?'));
        $this->assertCount(0, $lazySchedules);
        DB::disableQueryLog();
    }

    public function test_team_venue_grouping_uses_published_snapshot_and_excludes_foreign_event(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $publicVenue = Venue::forceCreate(['name' => 'Public snapshot venue']);
        $privateVenue = Venue::forceCreate(['name' => 'Private revised venue']);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1,
            'venue_id' => $privateVenue->id, 'scheduled_at' => '2026-10-11 10:00:00']);
        DB::table('published_schedule_assignments')->insert([
            'event_id' => $event->id, 'draw_id' => $draw->id, 'fixture_kind' => 'team',
            'fixture_id' => $fixture->id, 'venue_id' => $publicVenue->id,
            'scheduled_at' => '2026-10-10 08:00:00', 'court' => '1', 'duration' => 120,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignDraw = Draw::factory()->create(['published' => true]);
        TeamFixture::create(['draw_id' => $foreignDraw->id, 'match_nr' => 1, 'venue_id' => $privateVenue->id]);

        $response = $this->get('/events/'.$event->id)->assertOk();
        $groups = $response->viewData('fixturesPerVenueGrouped');
        $this->assertSame(['Public snapshot venue'], $groups->keys()->all());
        $this->assertSame([$fixture->id], $groups->first()->pluck('id')->all());
        $this->assertSame('2026-10-10 08:00:00', $groups->first()->first()->scheduled_at->format('Y-m-d H:i:s'));
    }
}
