<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventType, Fixture, Player, Registration, TrialProgramme, TrialRankingRun, TrialSquadDraft, User};
use App\Services\InterprovincialTrials\{TrialProgrammeService, TrialSelectionReviewService, TrialSquadService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrialProgrammeSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $count = 20, array $colours = [1, 2, 7, 8, 13, 14]): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $players = collect(); $positions = [];
        for ($i = 1; $i <= $count; $i++) {
            $player = Player::factory()->create(['is_player_of_colour' => in_array($i, $colours)]);
            $players->push($player);
            $registration = Registration::factory()->create(); $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
            $positions[] = ['category_event_id' => $category->id, 'player_id' => $player->id, 'registration_id' => $registration->id, 'name' => $player->full_name, 'position' => $i];
        }
        $run = TrialRankingRun::create(['event_id' => $event->id, 'signature' => hash('sha256', json_encode($positions)), 'positions' => $positions]);
        $programme = TrialProgramme::create(['event_id' => $event->id, 'current_run_id' => $run->id, 'concluded_at' => now()]);
        return compact('event', 'admin', 'category', 'players', 'run', 'programme', 'positions');
    }

    public function test_merit_colour_counts_and_only_lowest_team_has_four_reserves(): void
    {
        $f = $this->scenario();
        $draft = app(TrialSquadService::class)->generate($f['event'], ['A', 'B'], $f['admin']);
        $this->assertSame($f['players']->take(6)->pluck('id')->all(), $draft->slots()->where('tier', 'A')->orderBy('slot')->pluck('player_id')->all());
        $this->assertSame(4, $draft->slots()->where('reserve', true)->count());
        $this->assertSame(['B'], $draft->slots()->where('reserve', true)->pluck('tier')->unique()->values()->all());
        $this->assertSame('draft', $draft->status);
        $this->assertDatabaseCount('trial_squad_slots', 16);
    }

    public function test_required_vacancies_need_explicit_acknowledgement_and_swap_rolls_back(): void
    {
        $f = $this->scenario(8, []); $service = app(TrialSquadService::class);
        $draft = $service->generate($f['event'], ['A'], $f['admin']);
        $this->assertSame(2, $draft->slots()->where('requires_colour', true)->whereNull('player_id')->count());
        try { $service->finalise($draft, $f['admin'], false); $this->fail('Vacancies require acknowledgement.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('vacancies', $e->errors()); }
        $vacancy = $draft->slots()->where('slot', 5)->sole(); $reserve = $draft->slots()->where('slot', 7)->sole();
        try { $service->swap($draft, $vacancy, $reserve, $f['admin'], 'Try replacement'); $this->fail('Must retain colour minimum.'); }
        catch (ValidationException $e) { $this->assertNull($vacancy->fresh()->player_id); $this->assertNotNull($reserve->fresh()->player_id); }
        $service->finalise($draft, $f['admin'], true);
        $this->assertSame('finalised', $draft->fresh()->status);
    }

    public function test_unrelated_response_is_logged_admin_can_reverse_and_proposal_does_not_change_roster(): void
    {
        $f = $this->scenario(); $squads = app(TrialSquadService::class);
        $draft = $squads->generate($f['event'], ['A', 'B'], $f['admin']); $squads->finalise($draft, $f['admin'], false);
        $slot = $draft->slots()->where('tier', 'A')->where('slot', 3)->sole(); $actor = User::factory()->create();
        $service = app(TrialSelectionReviewService::class); $service->respond($slot, $actor, 'declined');
        $this->assertSame($actor->id, $slot->fresh()->responded_by);
        $proposal = $service->propose($slot->fresh(), $f['admin']);
        $this->assertSame($f['players'][6]->id, $proposal->source_player_id);
        $this->assertSame($f['players'][2]->id, $slot->fresh()->player_id);
        $service->respond($slot->fresh(), $f['admin'], 'confirmed', 'Family corrected their response');
        try { $service->approve($proposal, $f['admin'], 'Promote'); $this->fail('Stale decline must reject approval.'); }
        catch (ValidationException $e) { $this->assertSame('pending', $proposal->fresh()->status); }
    }

    public function test_incomplete_fixtures_invalidate_conclusion_then_manual_positions_and_withdrawn_policy_publish(): void
    {
        $f = $this->scenario(2, [1, 2]);
        $draw = Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['category']->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => $f['positions'][1]['registration_id']]);
        $service = app(TrialProgrammeService::class);
        $this->assertNull($service->refresh($f['event'])); $this->assertNull($f['programme']->fresh()->concluded_at);
        $fixture->update(['match_status' => 1, 'winner_registration' => $fixture->registration1_id]);
        $f['programme']->update(['manual_position_categories' => [$f['category']->id]]);
        foreach (array_reverse($f['positions']) as $i => $row) { DB::table('category_results')->insert(['event_id' => $f['event']->id, 'category_id' => $f['category']->category_id, 'registration_id' => $row['registration_id'], 'position' => $i + 1]); }
        $run = $service->refresh($f['event']);
        $this->assertSame($f['players'][1]->id, $run->positions[0]['player_id']);
        $entry = CategoryEventRegistration::where('registration_id', $f['positions'][1]['registration_id'])->sole();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        DB::table('trial_ranking_dispositions')->insert(['entry_id' => $entry->id, 'event_id' => $f['event']->id, 'disposition' => 'last', 'changed_by' => $f['admin']->id, 'reason' => 'Withdrawal']);
        $this->assertSame($f['players'][0]->id, $service->refresh($f['event'])->positions[0]['player_id']);
        DB::table('trial_ranking_dispositions')->where('entry_id', $entry->id)->update(['disposition' => 'exclude']);
        $this->assertCount(1, $service->refresh($f['event'])->positions);
        $this->assertDatabaseCount('trial_ranking_runs', 4);
    }

    public function test_excluded_finalised_player_can_be_removed_then_corrected_roster_reviewed(): void
    {
        $f = $this->scenario(); $service = app(TrialSquadService::class);
        $draft = $service->generate($f['event'], ['A'], $f['admin']); $service->finalise($draft, $f['admin'], false);
        $slot = $draft->slots()->where('slot', 1)->sole();
        $run = TrialRankingRun::create(['event_id' => $f['event']->id, 'signature' => str_repeat('b', 64), 'positions' => array_values(array_filter($f['positions'], fn ($row) => $row['player_id'] !== $slot->player_id))]);
        $f['programme']->update(['current_run_id' => $run->id]); $draft->update(['needs_review' => true]);
        app(TrialSelectionReviewService::class)->remove($slot, $f['admin'], 'Excluded from corrected Trials rankings');
        $service->acknowledgeCorrections($draft->fresh(), $f['admin'], 'Reviewed corrected roster and vacancy');
        $this->assertNull($slot->fresh()->player_id); $this->assertFalse($draft->fresh()->needs_review);
        $this->assertSame('finalised', $draft->fresh()->status);
    }

    public function test_trial_withdrawal_awards_remaining_match_loss_and_keeps_completed_scores(): void
    {
        $f = $this->scenario(3, [1, 2]);
        $draw = Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['category']->id]);
        $draw->registrations()->attach(collect($f['positions'])->pluck('registration_id'));
        $finished = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => $f['positions'][1]['registration_id'], 'match_status' => 1, 'winner_registration' => $f['positions'][0]['registration_id']]);
        $remaining = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => $f['positions'][2]['registration_id']]);
        $entry = CategoryEventRegistration::where('registration_id', $f['positions'][0]['registration_id'])->sole();
        app(\App\Domain\Entries\Services\EntryService::class)->withdrawEntryAsAdmin($entry, $f['admin']);
        $this->assertSame(3, $remaining->fresh()->match_status);
        $this->assertSame($f['positions'][2]['registration_id'], $remaining->fresh()->winner_registration);
        $this->assertSame(1, $finished->fresh()->match_status);
        $this->assertSame($f['positions'][0]['registration_id'], $finished->fresh()->winner_registration);
        $this->assertFalse($draw->registrations()->where('registrations.id', $entry->registration_id)->exists());
        $this->assertSame('withdrawn', $entry->fresh()->status); $this->assertTrue($entry->fresh()->is_paid);
    }

    public function test_approved_paid_b_to_a_replacement_reuses_payment_and_withdraws_displaced_player(): void
    {
        $f = $this->scenario();
        $f['programme']->update(['participation_fee' => 100]);
        $squads = app(TrialSquadService::class);
        $draft = $squads->generate($f['event'], ['A', 'B'], $f['admin']);
        $squads->finalise($draft, $f['admin'], false);
        $target = $draft->slots()->where('tier', 'A')->where('slot', 3)->sole();
        $source = $draft->slots()->where('tier', 'B')->where('slot', 1)->sole();
        $payments = app(\App\Services\InterprovincialTrials\TrialParticipationService::class);
        $payer = User::factory()->create();
        $displaced = $payments->begin($target, $payer);
        $promoted = $payments->begin($source, $payer);
        $payments->markPaid($displaced, $f['admin'], 'A-PAID');
        $payments->markPaid($promoted, $f['admin'], 'B-PAID');
        $review = app(TrialSelectionReviewService::class);
        $review->respond($target, $payer, 'declined');
        $proposal = $review->propose($target->fresh(), $f['admin']);
        $review->approve($proposal, $f['admin'], 'Promote next eligible player');
        $review->approve($proposal->fresh(), $f['admin'], 'Idempotent approval');
        $this->assertSame($promoted->player_id, $target->fresh()->player_id);
        $this->assertNull($source->fresh()->player_id);
        $this->assertSame($target->id, $promoted->fresh()->slot_id);
        $this->assertTrue($promoted->fresh()->isPaid());
        $this->assertNotNull($displaced->order->fresh()->withdrawn_at);
        $this->assertSame($promoted->order_id, $payments->begin($target->fresh(), $payer)->order_id);
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseCount('trial_participation_receipts', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_later_qualifying_opponent_automatically_receives_withdrawal_win(): void
    {
        $f = $this->scenario(2, [1, 2]);
        $draw = Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['category']->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => 0]);
        $entry = CategoryEventRegistration::where('registration_id', $f['positions'][0]['registration_id'])->sole();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $fixture->update(['registration2_id' => $f['positions'][1]['registration_id']]);
        app(TrialProgrammeService::class)->refresh($f['event']);
        $this->assertSame(3, $fixture->fresh()->match_status);
        $this->assertSame($f['positions'][1]['registration_id'], $fixture->fresh()->winner_registration);
    }

    public function test_two_withdrawn_players_finish_without_an_invalid_winner(): void
    {
        $f = $this->scenario(2, [1, 2]);
        $draw = Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['category']->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => $f['positions'][1]['registration_id']]);
        CategoryEventRegistration::whereIn('registration_id', collect($f['positions'])->pluck('registration_id'))->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $entry = CategoryEventRegistration::where('registration_id', $f['positions'][0]['registration_id'])->sole();
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry);
        $this->assertSame(5, $fixture->fresh()->match_status);
        $this->assertNull($fixture->fresh()->winner_registration);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry);
        $this->assertSame(5, $fixture->fresh()->match_status);
    }

    public function test_changed_later_category_manual_approval_is_invalidated_despite_earlier_incomplete_category(): void
    {
        $f = $this->scenario(2, [1, 2]);
        $later = CategoryEvent::factory()->create(['event_id' => $f['event']->id]);
        $f['programme']->update(['manual_position_categories' => [$later->id]]);
        $service = app(TrialProgrammeService::class);
        // The earlier category has paid entries but no completed draw.
        $this->assertNull($service->refresh($f['event'], true, [$later->id]));
        $this->assertSame([], $f['programme']->fresh()->manual_position_categories);
        $this->assertNull($service->refresh($f['event'], true, [$f['category']->id]));
        $this->assertSame([], $f['programme']->fresh()->manual_position_categories);
    }

    public function test_double_withdrawal_bracket_feeder_allows_surviving_qualifier_to_advance(): void
    {
        $f = $this->scenario(4, [1, 2]);
        $draw = Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['category']->id]);
        $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 3, 'registration1_id' => null, 'registration2_id' => $f['positions'][2]['registration_id']]);
        $out = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 1, 'parent_fixture_id' => $parent->id, 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => $f['positions'][1]['registration_id']]);
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1, 'match_nr' => 2, 'parent_fixture_id' => $parent->id, 'registration1_id' => $f['positions'][2]['registration_id'], 'registration2_id' => $f['positions'][3]['registration_id'], 'match_status' => 1, 'winner_registration' => $f['positions'][2]['registration_id']]);
        CategoryEventRegistration::whereIn('registration_id', array_column(array_slice($f['positions'], 0, 2), 'registration_id'))->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve(CategoryEventRegistration::where('registration_id', $f['positions'][0]['registration_id'])->sole());
        $this->assertSame(5, $out->fresh()->match_status);
        $this->assertNull($parent->fresh()->registration1_id);
        $this->assertSame(3, $parent->fresh()->match_status);
        $this->assertSame($f['positions'][2]['registration_id'], $parent->fresh()->winner_registration);
    }

    public function test_committed_final_result_automatically_revises_rankings_and_flags_existing_draft(): void
    {
        $f = $this->scenario(2, [1, 2]);
        $draft = app(TrialSquadService::class)->generate($f['event'], ['A'], $f['admin']);
        $draw = Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['category']->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'position' => 1, 'registration1_id' => $f['positions'][0]['registration_id'], 'registration2_id' => $f['positions'][1]['registration_id']]);
        DB::commit();
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        $fixture->update(['match_status' => 1, 'winner_registration' => $f['positions'][1]['registration_id']]);
        app(\App\Services\InterprovincialTrials\TrialRefreshQueue::class)->flush();
        $programme = $f['programme']->fresh('currentRun');
        $this->assertNotNull($programme->concluded_at);
        $this->assertSame($f['players'][1]->id, $programme->currentRun->positions[0]['player_id']);
        $this->assertTrue($draft->fresh()->needs_review);
        $this->assertDatabaseCount('trial_ranking_runs', 2);
        app(\App\Services\InterprovincialTrials\TrialRefreshQueue::class)->flush();
        $this->assertDatabaseCount('trial_ranking_runs', 2);
    }
}
