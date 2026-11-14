<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, Player, Registration, User, OrderOfPlay};
use App\Services\Draw\{DrawFinalPlacementService, OddPlayerPlayoffService};
use App\Services\Scheduling\RoundRobinPlayoffScheduleService;
use App\Services\DrawService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OddPlayerPlayoffTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'num_sets' => 1, 'score_format' => 'timed',
            'playoff_config' => collect(['main','plate','cons'])->map(fn ($slug, $i) => ['slug' => $slug, 'name' => $slug,
                'enabled' => true, 'size' => 2, 'positions' => [$i * 2 + 1, $i * 2 + 2]])->all()]);
        $group = $draw->groups()->create(['name' => 'A']);
        $registrations = collect(range(1, 7))->map(function ($i) use ($category, $group) {
            $reg = Registration::factory()->create();
            $reg->players()->attach(Player::factory()->create(['name' => 'Entrant '.$i])->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $reg->id, 'payment_status_id' => 1]);
            $group->registrations()->attach($reg->id, ['seed' => $i]);
            return $reg;
        });
        foreach ($registrations as $i => $one) foreach ($registrations as $j => $two) if ($i < $j) {
            $f = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
                'registration1_id' => $one->id, 'registration2_id' => $two->id, 'winner_registration' => $one->id, 'match_status' => 1]);
            $f->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 10, 'registration2_score' => 5,
                'winner_registration' => $one->id, 'loser_registration' => $two->id]);
        }
        app(RoundRobinPlayoffScheduleService::class)->prepare($draw);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        return [$draw->fresh(), $actor, $registrations, $group];
    }

    public function test_opt_in_preserves_fixtures_scores_and_times_and_advances_preliminary_winner(): void
    {
        [$draw, $actor, $regs, $group] = $this->scenario();
        $service = app(OddPlayerPlayoffService::class);
        $final = $draw->drawFixtures()->where('stage', 'CONS')->firstOrFail();
        $venue = \App\Models\Venue::forceCreate(['name' => 'Test courts']);
        $booking = OrderOfPlay::create(['fixture_id' => $final->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'time' => '2026-11-14 12:00:00', 'court' => '2']);
        $ids = $draw->drawFixtures()->pluck('id')->all();
        $scores = DB::table('fixture_results')->get()->toJson();
        $beforeBooking = $booking->fresh()->getAttributes();
        $plan = $service->review($draw, $draw->event_id);
        $result = $service->apply($draw, $draw->event_id, $plan['snapshot'], $actor);
        $this->assertSame($ids, $draw->drawFixtures()->whereIn('id', $ids)->pluck('id')->all());
        $this->assertSame($scores, DB::table('fixture_results')->get()->toJson());
        $this->assertSame($beforeBooking, $booking->fresh()->getAttributes());
        $this->assertSame(25, $draw->drawFixtures()->count());
        $again = $service->review($draw->fresh(), $draw->event_id);
        $this->assertTrue($service->apply($draw, $draw->event_id, $again['snapshot'], $actor)['already_applied']);
        $this->assertSame(25, $draw->drawFixtures()->count());
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $progressPayload = ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot']];
        $this->postJson(route('backend.draw.progress', $draw), $progressPayload)->assertOk()->assertJsonPath('progress.created', 0);
        $this->postJson(route('backend.draw.progress', $draw), $progressPayload)->assertOk();
        $this->assertSame(25, $draw->drawFixtures()->count());
        $this->assertNull($final->fresh()->winner_registration);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->assertSee('Winner of preliminary');
        $preliminary = Fixture::findOrFail($result['added_fixture_id']);
        $this->assertSame($regs[6]->id, $preliminary->registration1_id);
        $this->assertSame($regs[5]->id, $preliminary->registration2_id);
        app(DrawService::class)->saveBracketScore($preliminary, [[12, 10]]);
        $this->assertSame($regs[6]->id, $final->fresh()->registration1_id);
        $this->assertSame($regs[4]->id, $final->fresh()->registration2_id);
        app(DrawService::class)->saveBracketScore($final->fresh(), [[8, 11]]);
        foreach (['MAIN', 'PLATE'] as $stage) app(DrawService::class)->saveBracketScore($draw->drawFixtures()->where('stage', $stage)->firstOrFail(), [[10, 5]]);
        $placements = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->keyBy('position');
        $this->assertCount(7, $placements);
        $this->assertSame($regs[4]->id, $placements[5]['registration_id']);
        $this->assertSame($regs[6]->id, $placements[6]['registration_id']);
        $this->assertSame($regs[5]->id, $placements[7]['registration_id']);
        $this->assertSame($beforeBooking, $booking->fresh()->getAttributes());
        $public = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk();
        foreach ($regs as $registration) $public->assertSee($registration->display_name);
        $public->assertDontSee('Awaiting result');
        $svg = (new \App\Services\DynamicBracketEngine($draw->fresh()))->build();
        $tail = collect($svg['brackets'])->firstWhere('slug', 'cons');
        $this->assertCount(1, $tail['rounds'][1]); $this->assertCount(1, $tail['rounds'][2]);
    }

    public function test_normal_pair_draw_includes_unpaired_last_place_and_pending_sources_are_not_byes(): void
    {
        [$draw, , $regs] = $this->scenario();
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw)->keyBy('position');
        $this->assertCount(7, $positions);
        $this->assertSame($regs[6]->id, $positions[7]['registration_id']);
        $page = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk();
        $page->assertSee('Awaiting #1')->assertSee('Awaiting #6');
        $this->assertStringNotContainsString('>BYE<', $page->getContent());
    }

    public function test_apply_rejects_foreign_event_unauthorized_actor_stale_snapshot_and_played_tail(): void
    {
        [$draw, $actor] = $this->scenario();
        $service = app(OddPlayerPlayoffService::class);
        $plan = $service->review($draw, $draw->event_id);
        $url = route('backend.draw.odd-player-playoff', $draw);
        $payload = ['event_id' => $draw->event_id, 'snapshot' => $plan['snapshot'], 'confirmed' => 1];
        $this->post($url, array_replace($payload, ['event_id' => $draw->event_id + 1]))->assertStatus(422);
        $this->post($url, array_replace($payload, ['snapshot' => str_repeat('a',64)]))->assertStatus(409);
        $this->actingAs(User::factory()->create())->post($url, $payload)->assertForbidden();
        $this->actingAs($actor);
        $draw->drawFixtures()->where('stage','CONS')->update(['registration1_id' => Registration::factory()->create()->id]);
        $this->post($url, $payload)->assertStatus(422);
        $this->assertSame(24, $draw->drawFixtures()->count());
    }

    public function test_scoreless_walkover_group_can_resolve_qualifiers_and_partial_sets_cannot(): void
    {
        [$draw] = $this->scenario();
        $walkover = $draw->drawFixtures()->where('stage', 'RR')->firstOrFail();
        $walkover->fixtureResults()->delete();
        $walkover->update(['match_status' => 3]);
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $this->postJson(route('backend.draw.progress', $draw), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot']])->assertOk();
        $this->assertSame(3, $draw->drawFixtures()->where('stage', '!=', 'RR')->whereNotNull('registration1_id')->whereNotNull('registration2_id')->count());
        $draw->settings->update(['score_format' => null, 'num_sets' => 3]);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertStatus(422);
    }

    public function test_settings_offer_opt_in_and_retain_its_marker_without_allowing_generic_creation_or_removal(): void
    {
        [$draw] = $this->scenario();
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Enable odd-player playoff');
        $plan = app(OddPlayerPlayoffService::class)->review($draw, $draw->event_id);
        $this->post(route('backend.draw.odd-player-playoff', $draw), ['event_id' => $draw->event_id,
            'snapshot' => $plan['snapshot'], 'confirmed' => 1])->assertRedirect();
        $config = $draw->fresh()->settings->playoff_config;
        $this->assertTrue(collect($config)->last()['odd_tail']);
        // With no recorded results, both ordinary settings writers may round-trip
        // this existing option. They cannot invent or remove its topology.
        DB::table('fixture_results')->whereIn('fixture_id', $draw->drawFixtures()->pluck('id'))->delete();
        $draw->drawFixtures()->update(['winner_registration' => null, 'match_status' => 0]);
        $this->postJson(route('backend.draw.update-settings', $draw), ['playoff_config' => $config,
            'match_duration_minutes' => 15, 'changeover_minutes' => 0])->assertOk();
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $config])->assertOk();
        $formConfig = collect($config)->map(function ($entry) { $entry['positions'] = array_map('strval', $entry['positions']); return $entry; })->all();
        $this->post(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $formConfig])->assertOk();
        $this->assertTrue(collect($draw->fresh()->settings->playoff_config)->last()['odd_tail']);
        $withoutFlag = $config; unset($withoutFlag[array_key_last($withoutFlag)]['odd_tail']);
        $this->postJson(route('backend.draw.update-settings', $draw), ['playoff_config' => $withoutFlag,
            'match_duration_minutes' => 15, 'changeover_minutes' => 0])->assertStatus(422);
        $draw->update(['published' => false, 'oop_published' => false]);
        $changedOtherPair = $config; $changedOtherPair[0]['positions'] = [2, 1];
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => $changedOtherPair])->assertStatus(422);
        $this->assertSame(25, $draw->drawFixtures()->count());
    }

    public function test_seed_badges_use_canonical_timed_standings_and_confirmed_sources_override_later_drift(): void
    {
        [$draw, , $regs] = $this->scenario();
        foreach ([[1,5,10],[2,5,5],[3,20,0]] as [$opponent,$home,$away]) {
            $fixture = $draw->drawFixtures()->where('stage','RR')->where('registration1_id',$regs[0]->id)
                ->where('registration2_id',$regs[$opponent]->id)->firstOrFail();
            $fixture->fixtureResults()->update(['registration1_score' => $home, 'registration2_score' => $away,
                'winner_registration' => $home === $away ? null : ($home > $away ? $regs[0]->id : $regs[$opponent]->id)]);
            $fixture->update(['winner_registration' => $home === $away ? null : ($home > $away ? $regs[0]->id : $regs[$opponent]->id)]);
        }
        $data = (new \App\Services\DynamicBracketEngine($draw->fresh()))->build();
        $this->assertSame('A2', $data['seedOriginMap'][$regs[0]->id]);
        $this->assertSame('A3', $data['seedOriginMap'][$regs[2]->id]);
        $pair = $draw->drawFixtures()->where('stage','MAIN')->firstOrFail();
        $pair->update(['registration1_id' => $regs[0]->id]);
        $confirmed = (new \App\Services\DynamicBracketEngine($draw->fresh()))->build();
        $this->assertSame('A1', $confirmed['seedOriginMap'][$regs[0]->id]);
    }

    public function test_generic_pair_svg_keeps_ratings_on_separate_lines_and_seed_badges_inside_the_match(): void
    {
        [$draw, $actor] = $this->scenario();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor->assignRole('super-user');
        $this->mock(\App\Services\Performance\PlayerRatingBadgeService::class, function ($mock) {
            $mock->shouldReceive('forPlayer')->andReturn(['display_label' => '65.2 | Medium', 'title' => 'Fictional rating']);
        });
        $standings = app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw->fresh());
        app(RoundRobinPlayoffScheduleService::class)->resolveFromStandings($draw->fresh(), $standings, true);
        $html = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->getContent();
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($document);
        $names = $xpath->query('//svg//text[contains(@class,"player-name")]');
        $ratings = $xpath->query('//svg//text[@class="player-rating-line"]');
        $this->assertSame(6, $names->length);
        $this->assertSame(6, $ratings->length);
        foreach ($names as $name) $this->assertSame(0, $xpath->query('.//*[@data-rating-svg]', $name)->length);
        foreach ($ratings as $rating) $this->assertStringContainsString('65.2 | Medium', $rating->textContent);
        $this->assertSame(6, $xpath->query('//svg//text[@class="seed-origin"]')->length);
        foreach ($xpath->query('//svg//text[@class="seed-origin"]') as $seed) {
            $row = $xpath->query('../rect[@class="match-row-bg"]', $seed)->item(0);
            $this->assertSame((float) $row->getAttribute('x') + (float) $row->getAttribute('width') - 16,
                (float) $seed->getAttribute('x'));
        }
        foreach ($xpath->query('//svg//text[@class="id-red"]') as $id) $this->assertMatchesRegularExpression('/^#\d+$/', trim($id->textContent));
    }
}
