<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventScoringAccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'score-keeper', 'guard_name' => 'web']);
        DB::table('eventtypes')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Individual', 'type' => EventType::INDIVIDUAL]
        );
    }

    public function test_settings_exposes_a_separate_scoring_account_selector(): void
    {
        $viewer = User::factory()->create()->assignRole('super-user');
        $scorer = User::factory()->create(['email' => 'court-one@example.test'])
            ->assignRole('score-keeper');
        $event = Event::factory()->create();
        DB::table('event_convenors')->insert([
            'event_id' => $event->id,
            'user_id' => $scorer->id,
            'role' => 'score-keeper',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($viewer)
            ->get(route('admin.events.settings', $event))
            ->assertOk()
            ->assertSee('Scoring accounts')
            ->assertSee('score-entry access for this event only')
            ->assertSee('Access &amp; responsibilities', false)
            ->assertSee('userSearchUrl', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $option = $xpath->query('//select[@name="scoring_accounts"]/option[@value="'.$scorer->id.'"]')->item(0);

        $this->assertNotNull($option);
        $this->assertTrue($option->hasAttribute('selected'));
        $this->assertStringContainsString($scorer->email, $option->textContent);
    }

    public function test_settings_assigns_and_removes_event_scoped_scoring_access(): void
    {
        $viewer = User::factory()->create()->assignRole('super-user');
        $scorer = User::factory()->create();
        $director = User::factory()->create();
        $event = Event::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $otherEvent = Event::factory()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);
        $draw = Draw::factory()->create(['event_id' => $event->id]);

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $event), [
                'convenors' => [$scorer->id],
                'scoring_accounts' => [$scorer->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scoring_accounts');
        $this->assertFalse($scorer->fresh()->hasRole('score-keeper'));

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $event), [
                'convenors' => [$director->id],
                'scoring_accounts' => [$scorer->id],
                'scoring_starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
                'scoring_expires_at' => now()->addHour()->format('Y-m-d H:i:s'),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('event_convenors', [
            'event_id' => $event->id,
            'user_id' => $scorer->id,
            'role' => 'score-keeper',
        ]);
        $this->assertDatabaseHas('event_convenors', [
            'event_id' => $event->id,
            'user_id' => $director->id,
            'role' => 'hoof',
        ]);
        $this->assertTrue($scorer->fresh()->hasRole('score-keeper'));
        $this->assertTrue(Gate::forUser($scorer->fresh())->allows('event.score', $event));
        $this->assertFalse(Gate::forUser($scorer->fresh())->allows('event.score', $otherEvent));
        $this->assertTrue(Gate::forUser($scorer->fresh())->allows('saveScore', $draw));
        $this->assertFalse(Gate::forUser($scorer->fresh())->allows('update', $draw));
        $this->assertFalse(Gate::forUser($scorer->fresh())->allows('publish', $draw));
        $this->assertFalse(Gate::forUser($scorer->fresh())->allows('lockToggle', $draw));
        $this->assertEquals([$director->id], $event->convenors()->pluck('user_id')->all());
        $this->actingAs($scorer->fresh())
            ->get(route('admin.events.settings', $event))
            ->assertForbidden();
        $this->actingAs($scorer->fresh())
            ->patchJson(route('admin.events.settings.update', $event), ['name' => 'Unauthorized change'])
            ->assertForbidden();
        $this->assertNotSame('Unauthorized change', $event->fresh()->name);

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $event), [
                'convenors' => [$director->id],
                'scoring_accounts' => [],
            ])
            ->assertOk();

        $this->assertDatabaseMissing('event_convenors', [
            'event_id' => $event->id,
            'user_id' => $scorer->id,
        ]);
        $this->assertFalse($scorer->fresh()->hasRole('score-keeper'));
        $this->assertFalse(Gate::forUser($scorer->fresh())->allows('event.score', $event));
    }

    public function test_settings_can_limit_a_scoring_account_to_one_event_venue(): void
    {
        $viewer = User::factory()->create()->assignRole('super-user');
        $scorer = User::factory()->create();
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->forceFill(['name' => 'Court Centre'])->save();
        $otherVenue = new Venue();
        $otherVenue->forceFill(['name' => 'Other Event Venue'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 3]);

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $event), [
                'scoring_accounts' => [$scorer->id],
                'scoring_venues' => [$scorer->id => $venue->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('event_convenors', [
            'event_id' => $event->id,
            'user_id' => $scorer->id,
            'role' => 'score-keeper',
            'venue_id' => $venue->id,
        ]);

        $this->actingAs($viewer)
            ->get(route('admin.events.settings', $event))
            ->assertOk()
            ->assertSee('Venue responsibility')
            ->assertSee('Court Centre');

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $event), [
                'scoring_accounts' => [$scorer->id],
                'scoring_venues' => [$scorer->id => $otherVenue->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scoring_venues');

        $this->assertSame($venue->id, (int) DB::table('event_convenors')
            ->where('event_id', $event->id)
            ->where('user_id', $scorer->id)
            ->value('venue_id'));
    }

    public function test_access_windows_require_a_real_positive_duration(): void
    {
        $viewer = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $instant = now()->addDay()->startOfHour()->format('Y-m-d H:i:s');

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $event), [
                'convenor_starts_at' => $instant,
                'convenor_expires_at' => $instant,
                'scoring_starts_at' => $instant,
                'scoring_expires_at' => $instant,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['convenor_expires_at', 'scoring_expires_at']);
    }

    public function test_event_directors_cannot_open_or_mutate_sensitive_settings(): void
    {
        $director = User::factory()->create();
        $event = Event::factory()->create();
        DB::table('event_convenors')->insert([
            'event_id' => $event->id,
            'user_id' => $director->id,
            'role' => 'hoof',
            'starts_at' => now()->subHour(),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($director)
            ->get(route('admin.events.settings', $event))
            ->assertForbidden();
        $this->actingAs($director)
            ->patchJson(route('admin.events.settings.update', $event), ['name' => 'Escalated'])
            ->assertForbidden();
        $this->actingAs($director)
            ->getJson(route('admin.events.settings.users', $event))
            ->assertForbidden();
        $this->assertNotSame('Escalated', $event->fresh()->name);
    }

    public function test_scoring_account_search_is_bounded_and_excludes_administrators(): void
    {
        $viewer = User::factory()->create()->assignRole('super-user');
        $administrator = User::factory()->create([
            'name' => 'Searchable Administrator',
            'email' => 'searchable-admin@example.test',
        ])->assignRole('admin');
        User::factory()->count(25)->create();
        $event = Event::factory()->create();

        $response = $this->actingAs($viewer)
            ->getJson(route('admin.events.settings.users', [
                'event' => $event,
                'scope' => 'scoring',
            ]))
            ->assertOk()
            ->assertJsonCount(20, 'results');

        $this->assertNotContains($administrator->id, collect($response->json('results'))->pluck('id'));
    }

    public function test_convenor_account_can_score_one_event_and_manage_another(): void
    {
        Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);

        $viewer = User::factory()->create()->assignRole('super-user');
        $convenor = User::factory()->create([
            'name' => 'Convenor Scorer',
            'email' => 'convenor-scorer@example.test',
        ])->assignRole('convenor');
        $activeWindow = [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ];
        $scoringEvent = Event::factory()->create($activeWindow);
        $managedEvent = Event::factory()->create($activeWindow);
        $scoringDraw = Draw::factory()->create(['event_id' => $scoringEvent->id]);
        $managedDraw = Draw::factory()->create(['event_id' => $managedEvent->id]);

        DB::table('event_convenors')->insert([
            'event_id' => $managedEvent->id,
            'user_id' => $convenor->id,
            'role' => 'hoof',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->getJson(route('admin.events.settings.users', [
                'event' => $scoringEvent,
                'scope' => 'scoring',
                'q' => 'convenor-scorer@example.test',
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $convenor->id,
                'text' => 'Convenor Scorer (convenor-scorer@example.test)',
            ]);

        $this->actingAs($viewer)
            ->patchJson(route('admin.events.settings.update', $scoringEvent), [
                'scoring_accounts' => [$convenor->id],
            ])
            ->assertOk();

        $convenor = $convenor->fresh();
        $this->assertTrue($convenor->hasRole('convenor'));
        $this->assertTrue($convenor->hasRole('score-keeper'));
        $this->assertTrue(Gate::forUser($convenor)->allows('event.score', $scoringEvent));
        $this->assertFalse(Gate::forUser($convenor)->allows('event.manage', $scoringEvent));
        $this->assertTrue(Gate::forUser($convenor)->allows('saveScore', $scoringDraw));
        $this->assertFalse(Gate::forUser($convenor)->allows('update', $scoringDraw));

        $this->assertTrue(Gate::forUser($convenor)->allows('event.manage', $managedEvent));
        $this->assertTrue(Gate::forUser($convenor)->allows('update', $managedDraw));
    }
}
