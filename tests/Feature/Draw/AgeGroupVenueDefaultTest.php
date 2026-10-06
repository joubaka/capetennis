<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgeGroupVenueDefaultTest extends TestCase
{
    use RefreshDatabase;

    private function draw(Event $event, string $category): Draw
    {
        $category = Category::create(['name' => $category]);
        $pivot = CategoryEvent::create(['event_id' => $event->id, 'category_id' => $category->id]);
        return Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $pivot->id, 'published' => false, 'locked' => false]);
    }

    private function setupEvent(): array
    {
        $event = Event::factory()->create();
        $actor = User::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $venues = [];
        foreach (range(1, 3) as $number) {
            $id = DB::table('venues')->insertGetId(['name' => 'Venue '.$number, 'event_id' => $event->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('event_venues')->insert(['event_id' => $event->id, 'venue_id' => $id, 'num_courts' => 3]);
            $venues[] = $id;
        }
        return [$event, $actor, ['venue_id' => $venues, 'num_courts' => [2, 3, 1], 'age_group_default' => true]];
    }

    public function test_defaults_cover_all_matching_squads_and_future_draws_without_crossing_gender_age_or_event(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $source = $this->draw($event, 'u/10 Boys');
        $squad = $this->draw($event, 'Under 10 Boys B division');
        $others = [$this->draw($event, 'u/10 Girls'), $this->draw($event, 'u/11 Boys'), $this->draw(Event::factory()->create(), 'u/10 Boys')];
        $this->actingAs($actor)->post(route('backend.draw.venues.store', $source), $payload, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJsonCount(2, 'affected_draw_ids');
        $future = $this->draw($event, 'U10 Boys C division');
        foreach ([$source, $squad, $future] as $draw) {
            $this->assertEqualsCanonicalizing($payload['venue_id'], $draw->fresh()->venues->pluck('id')->all());
            $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $payload['venue_id'][1], 'num_courts' => 3]);
        }
        foreach ($others as $draw) $this->assertCount(0, $draw->fresh()->venues);
        $this->actingAs($actor)->post(route('backend.draw.venues.store', $source), $payload, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $this->assertDatabaseCount('event_age_group_venue_defaults', 1);
        $this->assertDatabaseCount('draw_venues', 9);
    }

    public function test_unauthorized_and_foreign_venues_cannot_set_defaults(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $source = $this->draw($event, 'u/10 Boys');
        $this->actingAs(User::factory()->create())->postJson(route('backend.draw.venues.store', $source), $payload)->assertForbidden();
        $foreign = DB::table('venues')->insertGetId(['name' => 'Foreign', 'event_id' => Event::factory()->create()->id, 'created_at' => now(), 'updated_at' => now()]);
        $payload['venue_id'][0] = $foreign;
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $source), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('event_age_group_venue_defaults', 0);
        $this->assertDatabaseCount('draw_venues', 0);
    }

    public function test_locked_or_published_matching_draw_rejects_bulk_update_atomically(): void
    {
        foreach (['locked', 'published'] as $flag) {
            [$event, $actor, $payload] = $this->setupEvent();
            $source = $this->draw($event, 'u/10 Boys');
            $protected = $this->draw($event, 'u/10 Boys B division');
            $protected->update([$flag => true]);
            $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $source), $payload)->assertUnprocessable();
            $this->assertDatabaseMissing('draw_venues', ['draw_id' => $source->id]);
            $this->assertDatabaseMissing('event_age_group_venue_defaults', ['event_id' => $event->id]);
        }
    }

    public function test_physical_allocations_remain_unchanged_on_rejected_update(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $source = $this->draw($event, 'u/10 Boys');
        $squad = $this->draw($event, 'u/10 Boys B division');
        $squad->venues()->attach($payload['venue_id'][0], ['num_courts' => 1]);
        DB::table('draw_venue_court_allocations')->insert(['draw_id' => $squad->id, 'venue_id' => $payload['venue_id'][0], 'court_label' => '1']);
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $source), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('draw_venues', 1);
        $this->assertDatabaseCount('draw_venue_court_allocations', 1);
        $this->assertDatabaseCount('event_age_group_venue_defaults', 0);
    }

    public function test_mixed_category_selection_and_missing_metadata_do_not_inherit_or_define_defaults(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $boys = $this->draw($event, 'u/10 Boys');
        $girls = $this->draw($event, 'u/10 Girls');
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $boys), $payload)->assertRedirect();
        $mixed = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $boys->category_event_id,
            'team_draw_selection' => ['category_ids' => [$boys->category_event_id, $girls->category_event_id]], 'published' => false, 'locked' => false]);
        $this->assertCount(0, $mixed->fresh()->venues);
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $mixed), $payload)->assertUnprocessable();
        $older = $this->draw($event, 'u/11 Boys');
        $mixed->team_draw_selection = ['category_ids' => [$boys->category_event_id, $older->category_event_id]];
        $mixed->save();
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $mixed), $payload)->assertUnprocessable();
    }

    public function test_groups_sort_numeric_ages_and_keep_matching_squads_contiguous(): void
    {
        $event = Event::factory()->create();
        $this->draw($event, 'u/11 Boys');
        $ten = $this->draw($event, 'u/10 Boys');
        $this->draw($event, 'u/9 Girls');
        $nine = $this->draw($event, 'u/9 Boys');
        $squad = $this->draw($event, 'Under 10 Boys B division');
        $groups = app(\App\Services\Scheduling\AgeGroupVenueDefaultService::class)->groups($event->fresh());
        $this->assertSame(['Under 9 Boys', 'Under 9 Girls', 'Under 10 Boys', 'Under 11 Boys'], $groups->keys()->all());
        $this->assertSame([$ten->id, $squad->id], $groups['Under 10 Boys']->pluck('id')->all());
        $this->assertSame([$nine->id], $groups['Under 9 Boys']->pluck('id')->all());
    }

    public function test_legacy_exact_configured_category_titles_resolve_but_ambiguous_titles_do_not(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $this->draw($event, 'u/10 Boys');
        $legacy = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => null,
            'drawName' => 'u/10 Boys – Singles', 'locked' => false, 'published' => false]);
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $legacy), $payload)->assertRedirect();
        $this->assertCount(3, $legacy->fresh()->venues);
        $unknown = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => null,
            'drawName' => 'u/10 Boys miscellaneous', 'locked' => false, 'published' => false]);
        $this->assertNull(app(\App\Services\Scheduling\AgeGroupVenueDefaultService::class)->key($unknown));
    }

    public function test_global_venue_can_be_selected_and_attached_to_the_event(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $source = $this->draw($event, 'u/10 Boys');
        DB::table('event_venues')->where('event_id', $event->id)->delete();
        $venueId = DB::table('venues')->insertGetId(['name' => 'Shared global venue', 'event_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $payload['venue_id'][0] = $venueId;
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $source), $payload)->assertRedirect();
        $this->assertDatabaseHas('event_venues', ['event_id' => $event->id, 'venue_id' => $venueId, 'num_courts' => 2]);
    }

    public function test_future_draw_does_not_restore_removed_venues_or_exceed_current_court_counts(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $source = $this->draw($event, 'u/10 Boys');
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $source), $payload)->assertRedirect();
        DB::table('event_venues')->where('event_id', $event->id)->where('venue_id', $payload['venue_id'][0])->delete();
        DB::table('event_venues')->where('event_id', $event->id)->where('venue_id', $payload['venue_id'][1])->update(['num_courts' => 1]);
        $future = $this->draw($event, 'u/10 Boys B division');
        $this->assertDatabaseMissing('draw_venues', ['draw_id' => $future->id, 'venue_id' => $payload['venue_id'][0]]);
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $future->id, 'venue_id' => $payload['venue_id'][1], 'num_courts' => 1]);
    }

    public function test_saved_bookings_are_preserved_and_the_whole_bulk_update_is_rejected(): void
    {
        [$event, $actor, $payload] = $this->setupEvent();
        $source = $this->draw($event, 'u/10 Boys');
        $squad = $this->draw($event, 'u/10 Boys B division');
        $squad->venues()->attach($payload['venue_id'][0], ['num_courts' => 1]);
        $fixtureId = DB::table('team_fixtures')->insertGetId(['draw_id' => $squad->id, 'venue_id' => $payload['venue_id'][0],
            'scheduled' => 1, 'scheduled_at' => '2026-10-06 09:00:00', 'court_label' => '1', 'round_nr' => 1, 'match_nr' => 1]);
        $this->actingAs($actor)->postJson(route('backend.draw.venues.store', $source), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('team_fixtures', ['id' => $fixtureId, 'scheduled' => 1, 'venue_id' => $payload['venue_id'][0], 'court_label' => '1']);
        $this->assertDatabaseCount('draw_venues', 1);
        $this->assertDatabaseCount('event_age_group_venue_defaults', 0);
    }
}
