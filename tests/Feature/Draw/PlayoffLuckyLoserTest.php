<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, DrawAuditLog, Event, Fixture, Player, Registration, User};
use App\Services\Draw\{PlayoffLuckyLoserService, InterproRoundRobinFormat};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayoffLuckyLoserTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['start_date' => '2026-11-27']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'U/19 Boys'])->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'locked' => false, 'published' => true]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $old = Registration::factory()->create();
        $old->players()->attach(Player::factory()->create()->id);
        $opponent = Registration::factory()->create();
        $opponent->players()->attach(Player::factory()->create()->id);
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->attach([$old->id, $opponent->id]);
        $draw->registrations()->attach([$old->id, $opponent->id]);
        $rr = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'draw_group_id' => $group->id,
            'registration1_id' => $old->id, 'registration2_id' => $opponent->id, 'winner_registration' => $old->id, 'match_status' => 1]);
        $rr->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => $old->id, 'loser_registration' => $opponent->id]);
        $target = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'registration1_id' => $old->id,
            'registration2_id' => $opponent->id, 'registration1_source_group_id' => $group->id,
            'registration1_source_position' => 1, 'winner_registration' => null, 'match_status' => 0]);
        $sourceCategory = CategoryEvent::factory()->create(['event_id' => $event->id,
            'category_id' => Category::factory()->create(['name' => 'U/10 Girls'])->id]);
        $source = Registration::factory()->create();
        $player = Player::factory()->create(['name' => 'Fictional', 'surname' => 'Replacement', 'gender' => 2, 'dateOfBirth' => '2017-01-01']);
        $source->players()->attach($player->id);
        $entry = CategoryEventRegistration::factory()->create(['category_event_id' => $sourceCategory->id, 'registration_id' => $source->id,
            'payment_status_id' => 1, 'status' => 'confirmed', 'withdrawn_at' => null]);
        return [$draw, $target, $entry, $rr];
    }

    private function payload(Draw $draw, Fixture $fixture, CategoryEventRegistration $entry): array
    {
        return ['target' => $fixture->id.':1:'.$fixture->registration1_id, 'source_entry_id' => $entry->id,
            'confirm_withdrawal' => 1, 'revision' => app(PlayoffLuckyLoserService::class)->revision($draw)];
    }

    public function test_cross_division_lucky_loser_preserves_round_robin_entries_and_original_player_and_survives_reresolution(): void
    {
        [$draw, $fixture, $entry, $rr] = $this->context();
        $payload = $this->payload($draw, $fixture, $entry);
        $savedResults = $rr->fixtureResults->toJson();
        $savedEntry = $entry->fresh()->toJson();
        $group = $rr->drawGroup->registrations->pluck('id')->all();
        $counts = [DB::table('category_event_registrations')->count(), DB::table('wallet_transactions')->count(), DB::table('registration_orders')->count()];
        $this->get(route('draw.playoff-lucky-loser.show', $draw))->assertOk()->assertSee('U/10 Girls')->assertSee('Fictional Replacement');
        $response = $this->postJson(route('draw.playoff-lucky-loser.store', $draw), $payload)->assertOk();
        $incoming = Registration::findOrFail($response->json('registration_id'));
        $this->assertSame('Fictional Replacement (LL)', $incoming->display_name);
        $this->assertSame('Fictional Replacement', $entry->registration->display_name);
        $this->assertSame('Replacement', $entry->registration->players->first()->fresh()->surname);
        $this->assertSame($savedResults, $rr->fresh()->fixtureResults->toJson());
        $this->assertSame($savedEntry, $entry->fresh()->toJson());
        $this->assertSame($group, $rr->drawGroup->fresh()->registrations->pluck('id')->all());
        $this->assertSame($rr->registration1_id, $rr->fresh()->registration1_id);
        $this->assertSame(0, $incoming->categoryEvents()->count());
        $this->assertSame($counts, [DB::table('category_event_registrations')->count(), DB::table('wallet_transactions')->count(), DB::table('registration_orders')->count()]);
        $this->postJson(route('draw.playoff-lucky-loser.store', $draw), $payload)->assertOk()->assertJsonPath('registration_id', $incoming->id);
        $this->assertSame(1, DrawAuditLog::where('draw_id', $draw->id)->where('action', 'playoff_lucky_loser_replaced')->count());
        app(\App\Services\Scheduling\RoundRobinPlayoffScheduleService::class)->resolveFromStandings($draw->fresh(), [$rr->draw_group_id => [['reg_id' => $rr->registration1_id], ['reg_id' => $rr->registration2_id]]], true);
        $this->assertSame($incoming->id, $fixture->fresh()->registration1_id);
        $this->assertSame($rr->registration1_id, $draw->fresh()->team_draw_selection['playoff_lucky_losers'][$fixture->id.':1']['outgoing_registration_id']);
    }

    public function test_requires_authority_confirmation_current_target_and_active_same_event_entry(): void
    {
        [$draw, $fixture, $entry] = $this->context();
        $url = route('draw.playoff-lucky-loser.store', $draw);
        $payload = $this->payload($draw, $fixture, $entry);
        $this->postJson($url, array_replace($payload, ['confirm_withdrawal' => 0]))->assertUnprocessable();
        $this->postJson($url, array_replace($payload, ['revision' => str_repeat('0', 64)]))->assertConflict();
        $entry->update(['withdrawn_at' => now(), 'status' => 'withdrawn']);
        $this->postJson($url, $payload)->assertConflict();
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertNotFound();
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->postJson($url, $payload)->assertForbidden();
        $this->assertSame((int) $fixture->registration1_id, (int) $fixture->fresh()->registration1_id);
        $this->assertSame(0, DrawAuditLog::where('action', 'playoff_lucky_loser_replaced')->count());
    }

    public function test_active_playoff_commitment_blocks_but_completed_loss_is_allowed(): void
    {
        [$draw, $fixture, $entry] = $this->context();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $commitment = Fixture::factory()->create(['draw_id' => $other->id, 'stage' => 'MAIN', 'registration1_id' => $entry->registration_id,
            'registration2_id' => $fixture->registration2_id, 'match_status' => 0, 'winner_registration' => null]);
        $url = route('draw.playoff-lucky-loser.store', $draw);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertUnprocessable();
        $other->settings()->create(['num_sets' => 1, 'score_format' => 'one_set_to_6']);
        $commitment->update(['match_status' => 1, 'winner_registration' => $fixture->registration2_id]);
        $commitment->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 2, 'registration2_score' => 6,
            'winner_registration' => $fixture->registration2_id, 'loser_registration' => $entry->registration_id]);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertOk();
        $this->assertSame($entry->registration_id, $commitment->fresh()->registration1_id);
    }

    public function test_started_playoff_locked_draw_and_foreign_target_are_rejected(): void
    {
        [$draw, $fixture, $entry] = $this->context();
        $url = route('draw.playoff-lucky-loser.store', $draw);
        $fixture->update(['match_status' => 2]);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertConflict();
        $fixture->update(['match_status' => 0]);
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $foreign = Fixture::factory()->create(['draw_id' => $other->id]);
        $this->postJson($url, array_replace($this->payload($draw, $fixture, $entry), ['target' => $foreign->id.':1:'.$fixture->registration1_id]))->assertUnprocessable();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $draw->update(['locked' => true]);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertConflict();
    }

    public function test_completed_semifinal_winner_or_loser_feed_blocks_until_the_candidate_has_no_remaining_route(): void
    {
        [$draw, $fixture, $entry] = $this->context();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $other->settings()->create(['num_sets' => 1, 'score_format' => 'one_set_to_6']);
        $final = Fixture::factory()->create(['draw_id' => $other->id, 'stage' => 'MAIN', 'registration1_id' => null,
            'registration2_id' => null, 'match_status' => 0, 'winner_registration' => null]);
        $semi = Fixture::factory()->create(['draw_id' => $other->id, 'stage' => 'MAIN', 'registration1_id' => $entry->registration_id,
            'registration2_id' => $fixture->registration2_id, 'match_status' => 1, 'winner_registration' => $entry->registration_id,
            'parent_fixture_id' => $final->id]);
        $set = $semi->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => $entry->registration_id, 'loser_registration' => $fixture->registration2_id]);
        $url = route('draw.playoff-lucky-loser.store', $draw);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertUnprocessable();
        $this->assertNull($final->fresh()->registration1_id);
        $this->assertSame(0, DrawAuditLog::where('action', 'playoff_lucky_loser_replaced')->count());
        // A semifinal loss still reserves a consolation place when that feeder exists.
        $semi->update(['winner_registration' => $fixture->registration2_id, 'loser_parent_fixture_id' => $final->id]);
        $set->update(['registration1_score' => 2, 'registration2_score' => 6,
            'winner_registration' => $fixture->registration2_id, 'loser_registration' => $entry->registration_id]);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertUnprocessable();
        // Without a loser destination, the player is eliminated and may be selected.
        $semi->update(['loser_parent_fixture_id' => null]);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertOk();
        $this->assertSame($entry->registration_id, $semi->fresh()->registration1_id);
        $this->assertNull($final->fresh()->registration1_id);
    }

    public function test_completed_round_robin_qualifier_owns_an_unresolved_prepared_playoff_slot(): void
    {
        [$draw, $fixture, $entry] = $this->context();
        $other = Draw::factory()->create(['event_id' => $draw->event_id, 'category_event_id' => $entry->category_event_id]);
        $other->settings()->create(['workflow' => 'round_robin_playoffs', 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
        $group = $other->groups()->create(['name' => 'Other group']);
        $group->registrations()->attach([$entry->registration_id, $fixture->registration2_id]);
        $roundRobin = Fixture::factory()->create(['draw_id' => $other->id, 'stage' => 'RR', 'draw_group_id' => $group->id,
            'registration1_id' => $entry->registration_id, 'registration2_id' => $fixture->registration2_id,
            'match_status' => 1, 'winner_registration' => $entry->registration_id]);
        $roundRobin->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => $entry->registration_id, 'loser_registration' => $fixture->registration2_id]);
        $pending = Fixture::factory()->create(['draw_id' => $other->id, 'stage' => 'MAIN',
            'registration1_id' => null, 'registration2_id' => null, 'match_status' => 0, 'winner_registration' => null,
            'registration1_source_group_id' => $group->id, 'registration1_source_position' => 1]);
        $url = route('draw.playoff-lucky-loser.store', $draw);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertUnprocessable();
        $this->assertNull($pending->fresh()->registration1_id);
        $pending->update(['registration1_source_position' => 2]);
        $this->postJson($url, $this->payload($draw, $fixture, $entry))->assertOk();
        $this->assertNull($pending->fresh()->registration1_id);
    }

    public function test_interpro_round_robin_rematch_allows_lucky_loser_but_playoff_rematch_rejects_it(): void
    {
        [$draw, $fixture, $entry, $rr] = $this->context();
        $draw->settings()->update(['preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration()]);
        $prior = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR',
            'registration1_id' => $fixture->registration2_id, 'registration2_id' => $entry->registration_id,
            'winner_registration' => $fixture->registration2_id, 'match_status' => 1]);
        $prior->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
            'winner_registration' => $fixture->registration2_id, 'loser_registration' => $entry->registration_id]);
        $url = route('draw.playoff-lucky-loser.store', $draw);
        $prior->update(['stage' => 'PLATE']);
        $this->postJson($url, $this->payload($draw->fresh(), $fixture, $entry))->assertConflict();
        $this->assertSame($rr->registration1_id, $fixture->fresh()->registration1_id);
        $prior->update(['stage' => 'RR']);
        $savedResults = $prior->fresh()->fixtureResults->toJson();
        $this->postJson($url, $this->payload($draw->fresh(), $fixture, $entry))->assertOk();
        $this->assertSame($savedResults, $prior->fresh()->fixtureResults->toJson());
        $this->assertDatabaseCount('draw_audit_logs', 1);
    }

    public function test_interpro_override_survives_resolve_and_alias_carry_keeps_original_identity_and_score_orientation(): void
    {
        [$draw, $fixture, $entry, $rr] = $this->context();
        $config = InterproRoundRobinFormat::configuration();
        $config[0]['trials_confirmed'] = true;
        $draw->settings()->update(['preset_key' => InterproRoundRobinFormat::PRESET, 'playoff_config' => $config]);
        $fixture->update(['match_nr' => 2001, 'registration2_source_group_id' => null, 'registration2_source_position' => null]);
        $groups = ['A' => $rr->draw_group_id];
        foreach (['B', 'C', 'D'] as $name) $groups[$name] = $draw->groups()->create(['name' => $name])->id;
        $standings = [];
        foreach ($groups as $id) {
            $standings[$id] = array_map(fn () => ['reg_id' => Registration::factory()->create()->id], range(1, 3));
        }
        $standings[$groups['A']][0]['reg_id'] = $rr->registration1_id;
        $standings[$groups['D']][0]['reg_id'] = $rr->registration2_id;
        foreach (InterproRoundRobinFormat::template($draw->fresh()) as $number => [$stage, $round]) {
            if ($number === 2001) continue;
            Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => $stage, 'round' => $round, 'match_nr' => $number,
                'registration1_id' => null, 'registration2_id' => null, 'match_status' => 0, 'winner_registration' => null]);
        }
        $response = $this->postJson(route('draw.playoff-lucky-loser.store', $draw), $this->payload($draw->fresh(), $fixture, $entry))->assertOk();
        $incoming = Registration::findOrFail($response->json('registration_id'));
        app(InterproRoundRobinFormat::class)->resolve($draw->fresh(), $standings);
        $this->assertSame($incoming->id, $fixture->fresh()->registration1_id);
        // A later meeting must recognize the physical player, despite the new competition ID.
        $original = $draw->drawFixtures()->where('match_nr', 3001)->firstOrFail();
        $original->update([
            'registration1_id' => $entry->registration_id, 'registration2_id' => $rr->registration2_id, 'match_status' => 1,
            'winner_registration' => $entry->registration_id]);
        $original->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 3,
            'winner_registration' => $entry->registration_id, 'loser_registration' => $rr->registration2_id]);
        $later = $draw->drawFixtures()->where('match_nr', 3009)->firstOrFail();
        $later->update(['registration1_id' => $incoming->id, 'registration2_id' => $rr->registration2_id]);
        $this->assertSame(InterproRoundRobinFormat::pair($original), InterproRoundRobinFormat::pair($later));
        $config = $draw->fresh()->settings->playoff_config;
        $config[0]['trials_carries'][$later->id] = ['source' => $original->id,
            'fingerprint' => (new \ReflectionMethod(InterproRoundRobinFormat::class, 'fingerprint'))->invoke(null, $original->fresh())];
        $draw->settings()->update(['playoff_config' => $config]);
        $later = $later->fresh();
        $this->assertSame($incoming->id, app(\App\Services\IndividualMatchOutcomeService::class)->winner($later));
        $this->assertStringStartsWith('6-3', InterproRoundRobinFormat::score($later));
        $this->assertSame(0, $later->fixtureResults()->count());
    }
}
