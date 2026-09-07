<?php

namespace Tests\Feature\Draw;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawRecoveryCase;
use App\Models\DrawRecoverySnapshot;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    public function test_ordinary_delete_is_blocked_when_a_descendant_has_been_played(): void
    {
        [$draw, $admin, $players] = $this->drawWithUsers();
        $final = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'MAIN',
            'round' => 2,
            'match_nr' => 3,
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[2]->id,
            'winner_registration' => $players[0]->id,
            'match_status' => 1,
        ]);
        $semi = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'MAIN',
            'round' => 1,
            'match_nr' => 1,
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[1]->id,
            'winner_registration' => $players[0]->id,
            'parent_fixture_id' => $final->id,
            'match_status' => 1,
        ]);
        $this->recordResult($semi, $players[0], $players[1], 6, 3);
        $this->recordResult($final, $players[0], $players[2], 6, 4);

        $this->actingAs($admin)
            ->deleteJson(route('api.draws.fixtures.score.delete', [$draw, $semi]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'This result feeds a later played match. Delete that downstream result first, or open tournament recovery to preview and reset the affected chain safely.');

        $this->assertDatabaseHas('fixture_results', ['fixture_id' => $semi->id]);
        $this->assertDatabaseHas('fixture_results', ['fixture_id' => $final->id]);
    }

    public function test_rr_winner_change_is_blocked_when_a_fixed_playoff_bracket_exists(): void
    {
        [$draw, $admin, $players] = $this->drawWithUsers();
        $rr = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'round' => 1,
            'match_nr' => 1,
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[1]->id,
            'winner_registration' => $players[0]->id,
            'match_status' => 1,
        ]);
        $this->recordResult($rr, $players[0], $players[1], 6, 3);
        Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'MAIN',
            'round' => 1,
            'match_nr' => 2,
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[2]->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('backend.roundrobin.score.store', $rr), ['sets' => ['3-6']])
            ->assertStatus(409)
            ->assertJsonPath('message', 'The round robin has a generated playoff bracket. Open tournament recovery so it can be snapshotted and rebuilt from corrected standings.');

        $this->assertSame($players[0]->id, $rr->fresh()->winner_registration);
    }

    public function test_played_playoff_recovery_requires_super_user_and_creates_restorable_snapshots(): void
    {
        [$draw, $admin, $players, $super] = $this->drawWithUsers(true);
        $rr = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'round' => 1,
            'match_nr' => 1,
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[1]->id,
            'winner_registration' => $players[0]->id,
            'match_status' => 1,
        ]);
        $this->recordResult($rr, $players[0], $players[1], 6, 3);
        $playoff = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'MAIN',
            'round' => 1,
            'match_nr' => 2,
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[2]->id,
            'winner_registration' => $players[0]->id,
            'match_status' => 1,
        ]);
        $this->recordResult($playoff, $players[0], $players[2], 6, 4);

        $preview = $this->actingAs($admin)
            ->postJson(route('backend.draw.recovery.preview', $draw), ['fixture_id' => $rr->id])
            ->assertOk()
            ->assertJsonPath('impact.scored_playoff_fixtures', 1)
            ->assertJsonPath('impact.requires_super_user', true)
            ->json();

        $payload = [
            'fixture_id' => $rr->id,
            'sets' => [[3, 6]],
            'reason' => 'The result was entered against the wrong player order.',
            'fingerprint' => $preview['fingerprint'],
            'confirmation' => 'RECOVER #'.$draw->id,
        ];
        $this->actingAs($admin)->postJson(route('backend.draw.recovery.apply', $draw), $payload)->assertForbidden();

        $freshPreview = $this->actingAs($super)
            ->postJson(route('backend.draw.recovery.preview', $draw), ['fixture_id' => $rr->id])
            ->assertOk()->json();
        $payload['fingerprint'] = $freshPreview['fingerprint'];
        $response = $this->postJson(route('backend.draw.recovery.apply', $draw), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $case = DrawRecoveryCase::findOrFail($response->json('recovery_case.id'));
        $this->assertSame('applied', $case->status);
        $this->assertNotNull($case->before_snapshot_id);
        $this->assertNotNull($case->after_snapshot_id);
        $this->assertSame(2, DrawRecoverySnapshot::where('recovery_case_id', $case->id)->count());
        $this->assertDatabaseMissing('fixtures', ['id' => $playoff->id]);
        $this->assertDatabaseHas('fixture_results', [
            'fixture_id' => $rr->id,
            'registration1_score' => 3,
            'registration2_score' => 6,
        ]);
        $this->assertSame($players[1]->id, $rr->fresh()->winner_registration);
        $this->assertTrue((bool) $draw->fresh()->locked);
        $this->assertFalse((bool) $draw->fresh()->published);

        $this->postJson(route('backend.draw.recovery.restore', [$draw, $case]), [
            'confirmation' => 'RESTORE #'.$case->id,
            'reason' => 'Revert the recovery after independent review.',
        ])->assertOk();

        $this->assertSame('restored', $case->fresh()->status);
        $this->assertSame($super->id, $case->fresh()->restored_by);
        $this->assertSame('Revert the recovery after independent review.', $case->fresh()->restore_reason);
        $this->assertDatabaseHas('fixtures', ['id' => $playoff->id, 'winner_registration' => $players[0]->id]);
        $this->assertDatabaseHas('fixture_results', [
            'fixture_id' => $rr->id,
            'registration1_score' => 6,
            'registration2_score' => 3,
        ]);
        $this->assertTrue((bool) $draw->fresh()->locked);
        $this->assertFalse((bool) $draw->fresh()->published);
    }

    public function test_recovery_audit_flags_a_winner_who_is_not_a_fixture_participant(): void
    {
        [$draw, , $players] = $this->drawWithUsers();
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'registration1_id' => $players[0]->id,
            'registration2_id' => $players[1]->id,
            'winner_registration' => $players[2]->id,
            'match_status' => 1,
        ]);

        $this->artisan('draw:recovery-audit', ['--draw' => $draw->id])
            ->expectsTable(['draw', 'severity', 'fixture', 'issue'], [[
                $draw->id,
                'CRITICAL',
                $fixture->id,
                'The fixture winner is not one of its participants.',
            ]])
            ->assertExitCode(1);
    }

    private function drawWithUsers(bool $published = false): array
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'published' => $published,
            'oop_published' => $published,
            'locked' => false,
        ]);
        $draw->settings()->create([
            'workflow' => 'round_robin_playoffs',
            'boxes' => 1,
            'num_sets' => 1,
            'playoff_config' => [[
                'name' => 'Main', 'slug' => 'main', 'size' => 4,
                'positions' => [1, 2], 'enabled' => true,
            ]],
        ]);

        $players = collect(range(1, 3))->map(function () use ($category) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create()->id);
            CategoryEventRegistration::factory()->create([
                'category_event_id' => $category->id,
                'registration_id' => $registration->id,
                'payment_status_id' => 1,
            ]);
            return $registration;
        })->all();

        $admin = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create()->assignRole('super-user');
        DB::table('event_admins')->insert([
            ['event_id' => $event->id, 'user_id' => $admin->id],
            ['event_id' => $event->id, 'user_id' => $super->id],
        ]);

        return [$draw, $admin, $players, $super];
    }

    private function recordResult(Fixture $fixture, Registration $winner, Registration $loser, int $one, int $two): void
    {
        FixtureResult::factory()->create([
            'fixture_id' => $fixture->id,
            'set_nr' => 1,
            'registration1_score' => $one,
            'registration2_score' => $two,
            'winner_registration' => $winner->id,
            'loser_registration' => $loser->id,
        ]);
    }
}
