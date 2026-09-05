<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
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
            ->assertSee('score-entry access for this event only');

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
}
