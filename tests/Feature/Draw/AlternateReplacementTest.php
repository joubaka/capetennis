<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, Fixture, FixtureResult, Player, Registration, User};
use App\Services\Draw\AlternateReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlternateReplacementTest extends TestCase
{
    use RefreshDatabase;

    private function setupDraw(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-10-30']);
        $category = Category::factory()->create(['name' => 'U/19 Boys']);
        $ce = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $ce->id, 'published' => true, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin', 'num_sets' => 1]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $old = Registration::factory()->create();
        $old->players()->attach(Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2009-01-01'])->id);
        $opponent = Registration::factory()->create();
        $opponent->players()->attach(Player::factory()->create()->id);
        $draw->registrations()->attach([$old->id, $opponent->id]);
        $player = Player::factory()->create(['name' => 'Incoming', 'surname' => 'Alternate', 'gender' => 1, 'dateOfBirth' => '2009-01-01']);
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->attach([$old->id, $opponent->id]);
        $past = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'registration1_id' => $old->id, 'registration2_id' => $opponent->id, 'winner_registration' => null, 'match_status' => 0]);
        $future = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'registration1_id' => $old->id, 'registration2_id' => $opponent->id, 'winner_registration' => null, 'match_status' => 0]);
        return [$draw, $old, $player, $past, $future];
    }

    public function test_preplay_alternate_replaces_all_positions_without_financial_changes(): void
    {
        [$draw, $old, $player, $past, $future] = $this->setupDraw();
        $pastName = $old->display_name;
        $entryCount = DB::table('category_event_registrations')->count();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id, 'show_alternate' => 1])->assertRedirect();
        $incoming = $future->fresh()->registration1;
        $this->assertSame('Incoming Alternate (alternate)', $incoming->display_name);
        $this->assertSame('Incoming Alternate (alternate)', $incoming->players->first()->full_name);
        $this->assertSame('Incoming Alternate', $player->fresh()->full_name);
        $this->assertSame($incoming->id, $past->fresh()->registration1_id);
        $this->assertSame($pastName, $old->fresh()->display_name);
        $this->assertSame($entryCount, DB::table('category_event_registrations')->count());
        $this->assertDatabaseCount('registration_order_items', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertStatus(422);
        $this->assertSame(1, count($draw->fresh()->team_draw_selection['singles_alternates']));
    }

    public function test_authorization_category_duplicate_and_locked_guards(): void
    {
        [$draw, $old, $player] = $this->setupDraw();
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $player->update(['gender' => 2]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $player->update(['gender' => 1, 'dateOfBirth' => '1990-01-01']);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $player->update(['dateOfBirth' => '2009-01-01']);
        $draw->update(['locked' => true]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertForbidden();
        $draw->update(['locked' => false]);
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->postJson(route('draw.alternate.store', $draw), $input)->assertForbidden();
        $this->assertNull($draw->fresh()->team_draw_selection);
    }

    public function test_optional_label_can_be_disabled(): void
    {
        [$draw, $old, $player, , $future] = $this->setupDraw();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertRedirect();
        $this->assertSame('Incoming Alternate', $future->fresh()->registration1->display_name);
        $this->get(route('draw.alternate.show', $draw, ['q' => 'Incoming']))->assertOk()->assertSee('Show “(alternate)”');
    }

    public function test_own_started_match_blocks_but_unrelated_completed_match_does_not(): void
    {
        [$draw, $old, $player, $first] = $this->setupDraw();
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $first->update(['match_status' => 2]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict();
        $first->update(['match_status' => 0]);
        FixtureResult::factory()->create(['fixture_id' => $first->id, 'set_nr' => 1, 'registration1_score' => 1, 'registration2_score' => 0]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict();
        $first->fixtureResults()->delete();
        $other = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id, 'match_status' => 1]);
        FixtureResult::factory()->create(['fixture_id' => $other->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $before = $other->fresh()->toArray();
        $this->post(route('draw.alternate.store', $draw), $input)->assertRedirect();
        $this->assertSame($before, $other->fresh()->toArray());
    }

    public function test_bye_and_opponent_feeder_do_not_block_first_match_alternate(): void
    {
        [$draw, $old, $player, $target] = $this->setupDraw();
        $bye = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $old->id, 'registration2_id' => null,
            'match_status' => 3, 'winner_registration' => $old->id, 'parent_fixture_id' => $target->id]);
        $opponent = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id, 'match_status' => 1, 'parent_fixture_id' => $target->id]);
        FixtureResult::factory()->create(['fixture_id' => $opponent->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertRedirect();
        $this->assertNotSame($old->id, $bye->fresh()->winner_registration);
    }

    public function test_stop_reset_does_not_erase_first_point_cutoff(): void
    {
        [$draw, $old, $player, $first] = $this->setupDraw();
        \App\Models\DrawAuditLog::create(['draw_id' => $draw->id, 'user_id' => auth()->id(), 'fixture_id' => $first->id, 'action' => 'match_started']);
        $this->postJson(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertConflict();
        $this->assertNull($draw->fresh()->team_draw_selection);
    }

    public function test_suspended_duplicate_and_cross_draw_source_are_rejected(): void
    {
        [$draw, $old, $player] = $this->setupDraw();
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $suspension = \App\Models\PlayerSuspension::create(['player_id' => $player->id, 'triggered_at' => today(), 'suspension_number' => 1,
            'duration_months' => 1, 'starts_at' => today(), 'ends_at' => today()->addMonth()]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $suspension->delete();
        $this->postJson(route('draw.alternate.store', $draw), array_replace($input, ['outgoing_registration_id' => Registration::factory()->create()->id]))->assertUnprocessable();
        $otherDraw = Draw::factory()->create(['event_id' => $draw->event_id, 'category_event_id' => $draw->category_event_id]);
        $reg = Registration::factory()->create();
        $reg->players()->attach($player->id);
        $otherDraw->registrations()->attach($reg->id);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $otherDraw->registrations()->detach();
        $draw->registrations()->attach($reg->id);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $this->assertNull($draw->fresh()->team_draw_selection);
    }

    public function test_trials_require_existing_active_paid_entry_and_leave_it_untouched(): void
    {
        [$draw, $old, $player] = $this->setupDraw();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $draw->event->update(['eventType' => $type]);
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $reg = Registration::factory()->create();
        $reg->players()->attach($player->id);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $reg->id, 'payment_status_id' => 1]);
        foreach (['refunded', 'cancelled', 'refund_requested', 'withdrawn'] as $inactive) {
            $entry->update(['status' => $inactive]);
            $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        }
        $entry->update(['status' => 'active']);
        $before = $entry->fresh()->toArray();
        $this->post(route('draw.alternate.store', $draw), $input)->assertRedirect();
        $this->assertSame($before, $entry->fresh()->toArray());
        $incomingId = $draw->fresh()->registrations()->whereHas('players', fn ($q) => $q->whereKey($player->id))->first()->id;
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry->fresh());
        $this->assertFalse($draw->registrations()->where('registrations.id', $incomingId)->exists());
        $this->assertFalse($draw->drawFixtures()->whereIn('match_status', [0, 2])->where(fn ($q) => $q->where('registration1_id', $incomingId)->orWhere('registration2_id', $incomingId))->exists());
    }

    public function test_normal_paid_alternate_withdrawal_removes_draw_participation(): void
    {
        [$draw, $old, $player, , $future] = $this->setupDraw();
        $reg = Registration::factory()->create();
        $reg->players()->attach($player->id);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $reg->id, 'payment_status_id' => 1]);
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertRedirect();
        $incomingId = $future->fresh()->registration1_id;
        app(\App\Domain\Entries\Services\EntryService::class)->withdrawEntryAsAdmin($entry->fresh(), auth()->user());
        $this->assertNull($future->fresh()->registration1_id);
        $this->assertFalse($draw->registrations()->where('registrations.id', $incomingId)->exists());
        $this->assertSame(1, $entry->fresh()->payment_status_id);
    }
}
