<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\DrawType;
use App\Models\TeamFixture;
use App\Models\Venue;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PublicDrawOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_discipline_sections_use_the_requested_order_for_published_and_unpublished_draws(): void
    {
        $user = \App\Models\User::factory()->create();
        Gate::define('event.score', fn () => false);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user->assignRole('super-user');
        $this->actingAs($user);
        $draws = collect();
        foreach ([true, false] as $published) {
            foreach (['Mixed Doubles', 'Doubles', 'Singles Reverse', 'Singles'] as $index => $discipline) {
                $draw = new Draw(['drawName' => 'Example '.$discipline, 'published' => $published, 'drawType_id' => $index + 1]);
                $draw->id = $draws->count() + 1;
                $draw->setRelation('draw_types', (new DrawType)->forceFill(['drawTypeName' => 'Team - '.$discipline]));
                $draw->setRelation('flexibleMonrad', null);
                $draw->setRelation('settings', null);
                $draws->push($draw);
            }
        }
        $event = new Event;
        $event->id = 1;
        $html = view('frontend.event.partials._draws_and_order_of_play', [
            'event' => $event, 'eventDraws' => $draws,
        ])->render();
        foreach (explode('Unpublished Draws</h6>', $html) as $section) {
            $previous = -1;
            foreach (['Singles', 'Singles Reverse', 'Doubles', 'Mixed Doubles'] as $discipline) {
                $position = strpos($section, '>Team - '.$discipline.'</h6>');
                $this->assertNotFalse($position);
                $this->assertGreaterThan($previous, $position);
                $previous = $position;
            }
        }
        $this->assertStringContainsString('Unpublished Draws</h6>', $html);
        $this->assertStringContainsString('event-play-group-title format-singles">Team - Singles</h6>', $html);
        $this->assertStringContainsString('event-play-group-title format-reverse-singles">Team - Singles Reverse</h6>', $html);
        $this->assertStringContainsString('event-draw-format">Singles Reverse</span>', $html);
    }

    public function test_published_draws_sort_by_numeric_age_and_name_without_exposing_unpublished_draws(): void
    {
        Gate::define('event.score', fn () => false);
        $names = ['u/13 Girls – Singles', 'u/10 Girls – Singles', 'u/12 Boys – Singles', 'u/9 Boys – Singles', 'u/10 Boys – Singles', 'Under 11 Girls – Singles'];
        $draws = collect($names)->map(function ($name, $index) {
            $draw = new Draw(['drawName' => $name, 'published' => true, 'drawType_id' => 1]);
            $draw->id = $index + 1;
            $draw->setRelation('draw_types', null);
            $draw->setRelation('flexibleMonrad', null);
            $draw->setRelation('settings', null);

            return $draw;
        });
        $hidden = new Draw(['drawName' => 'u/8 Hidden', 'published' => false]);
        $html = view('frontend.event.partials._draws_and_order_of_play', [
            'event' => new Event(['id' => 1]), 'eventDraws' => $draws->push($hidden),
        ])->render();

        $previous = -1;
        foreach (['u/9 Boys', 'u/10 Boys', 'u/10 Girls', 'Under 11 Girls', 'u/12 Boys', 'u/13 Girls'] as $label) {
            $position = strpos($html, $label);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($previous, $position);
            $previous = $position;
        }
        $this->assertStringNotContainsString('u/8 Hidden', $html);
    }

    public function test_public_type_sections_and_draws_follow_visible_snapshot_times_with_age_tie_breaks(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $venue = Venue::forceCreate(['name' => 'Published courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        // Reverse singles has the higher type ID, which previously put it first.
        $singles = DrawType::forceCreate(['drawTypeName' => 'Team - Singles', 'type' => 'team', 'btn_color' => 'primary']);
        $reverse = DrawType::forceCreate(['drawTypeName' => 'Team - Reverse Singles', 'type' => 'team', 'btn_color' => 'primary']);
        $noTimes = DrawType::forceCreate(['drawTypeName' => 'Team - No published times', 'type' => 'team', 'btn_color' => 'primary']);
        $draws = collect(); $fixtures = [];
        foreach ([
            ['u/10 Reverse', $reverse->id, '2026-10-09 10:00:00'],
            ['u/13 Early', $singles->id, '2026-10-09 08:00:00'],
            ['u/12 Same time', $singles->id, '2026-10-09 09:00:00'],
            ['u/10 Girls Same time', $singles->id, '2026-10-09 09:00:00'],
            ['u/10 Boys Same time', $singles->id, '2026-10-09 09:00:00'],
            ['u/9 Late', $singles->id, '2026-10-09 15:00:00'],
            ['u/8 Private only', $singles->id, null],
            ['u/7 No time', $noTimes->id, null],
        ] as [$name, $type, $time]) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => $name, 'drawType_id' => $type, 'published' => true]);
            $draws->push($draw);
            if ($time) $fixtures[$name] = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1,
                'fixture_type' => 1, 'scheduled_at' => $time, 'venue_id' => $venue->id, 'court_label' => '1']);
        }
        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['date' => '2026-10-09']);
        // Private changes must not reorder the public list.
        $fixtures['u/10 Reverse']->update(['scheduled_at' => '2026-10-08 06:00:00']);
        TeamFixture::create(['draw_id' => $draws->firstWhere('drawName', 'u/8 Private only')->id, 'round_nr' => 1, 'tie_nr' => 1,
            'match_nr' => 1, 'fixture_type' => 1, 'scheduled_at' => '2026-10-08 05:00:00', 'venue_id' => $venue->id, 'court_label' => '2']);
        $draws = Draw::whereKey($draws->pluck('id'))->with(['draw_types', 'settings', 'flexibleMonrad'])->get();
        $html = view('frontend.event.partials._draws_and_order_of_play', ['event' => $event, 'eventDraws' => $draws])->render();
        $this->assertStringNotContainsString('Venues and schedules', $html);
        $this->assertStringNotContainsString('Published courts', $html);
        $this->assertStringNotContainsString('Score fixtures by venue', $html);
        preg_match_all('/<article class="event-published-draw-row[^>]*>.*?<\/article>/s', $html, $cards);
        $earlyCard = collect($cards[0])->first(fn ($card) => str_contains($card, 'u/13 Early'));
        $privateCard = collect($cards[0])->first(fn ($card) => str_contains($card, 'u/8 Private only'));
        $this->assertNotNull($earlyCard);
        $this->assertNotNull($privateCard);
        $this->assertStringContainsString('is-scheduled', $earlyCard);
        $this->assertStringContainsString('Friday from 08:00', $earlyCard);
        $this->assertStringContainsString('is-unscheduled', $privateCard);
        $this->assertStringContainsString('Times not published', $privateCard);
        $this->assertLessThan(strpos($html, '>Team - Reverse Singles</h6>'), strpos($html, '>Team - Singles</h6>'));
        $this->assertLessThan(strpos($html, '>Team - No published times</h6>'), strpos($html, '>Team - Reverse Singles</h6>'));
        $previous = -1;
        foreach (['u/13 Early', 'u/10 Boys Same time', 'u/10 Girls Same time', 'u/12 Same time', 'u/9 Late', 'u/8 Private only'] as $label) {
            $position = strpos($html, 'event-published-draw-name">'.$label);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($previous, $position);
            $previous = $position;
        }
        // Sections remain grouped: later singles stays in its section ahead of reverse.
        $this->assertLessThan(strpos($html, 'event-published-draw-name">u/10 Reverse'), strpos($html, 'event-published-draw-name">u/9 Late'));
        $this->assertStringNotContainsString('Thursday', $html);
    }
}
