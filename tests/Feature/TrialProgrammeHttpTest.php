<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventType, Player, TrialProgramme, TrialRankingRun, TrialSquadDraft, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrialProgrammeHttpTest extends TestCase
{
    use RefreshDatabase;
    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create(['name' => 'PrivateSelection', 'surname' => 'Example']);
        $run = TrialRankingRun::create(['event_id' => $event->id, 'signature' => str_repeat('a', 64), 'positions' => []]);
        TrialProgramme::create(['event_id' => $event->id, 'bank_details' => 'PRIVATE ACCOUNT 998877', 'participation_fee' => 450]);
        $draft = TrialSquadDraft::create(['event_id' => $event->id, 'ranking_run_id' => $run->id, 'created_by' => $admin->id, 'tiers' => ['A'], 'status' => 'draft']);
        $slot = $draft->slots()->create(['category_event_id' => $category->id, 'tier' => 'A', 'slot' => 1, 'player_id' => $player->id]);
        return compact('event', 'admin', 'category', 'player', 'draft', 'slot');
    }

    public function test_programme_renders_actions_and_denies_unassigned_administrator(): void
    {
        $f = $this->scenario();
        $this->actingAs($f['admin'])->get(route('backend.interprovincial-trials.programme.index', $f['event']))
            ->assertOk()->assertSee('Regional payment settings')->assertSee('Invitations and reminders')->assertSee('administrative decisions');
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->get(route('backend.interprovincial-trials.programme.index', $f['event']))->assertForbidden();
    }

    public function test_public_page_hides_drafts_and_account_then_shows_finalised_roster_without_private_data(): void
    {
        $f = $this->scenario();
        $this->get(route('events.show', $f['event']))->assertOk()->assertDontSee($f['player']->full_name)->assertDontSee('PRIVATE ACCOUNT 998877');
        $f['draft']->update(['status' => 'finalised', 'finalised_at' => now(), 'finalised_by' => $f['admin']->id]);
        $this->get(route('events.show', $f['event']))->assertOk()->assertSee($f['player']->full_name)->assertSee('trial-squad-slot-'.$f['slot']->id)->assertDontSee('PRIVATE ACCOUNT 998877');
    }

    public function test_unrelated_eligible_user_can_respond_but_cross_event_and_private_roster_are_rejected(): void
    {
        $f = $this->scenario(); $actor = User::factory()->create();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs($actor)->post(route('interprovincial-trials.squads.respond', [$f['event'], $f['slot']]), ['response' => 'confirmed'])->assertNotFound();
        $f['draft']->update(['status' => 'finalised', 'finalised_at' => now()]);
        $other = Event::factory()->create(['eventType' => $f['event']->eventType, 'published' => true]);
        $this->post(route('interprovincial-trials.squads.respond', [$other, $f['slot']]), ['response' => 'confirmed'])->assertNotFound();
        $this->post(route('interprovincial-trials.squads.respond', [$f['event'], $f['slot']]), ['response' => 'confirmed'])->assertRedirect();
        $this->assertSame($actor->id, $f['slot']->fresh()->responded_by);
        $this->assertDatabaseMissing('user_players', ['user_id' => $actor->id, 'player_id' => $f['player']->id]);
    }
}
