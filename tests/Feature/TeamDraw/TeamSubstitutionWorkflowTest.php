<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, Event, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamFixturePlayer, TeamFixtureResult, TeamPlayer, TeamRegion, TeamSubstitution, TeamTie, User};
use App\Services\TeamDrawSideResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSubstitutionWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private Team $team;
    private Team $away;
    private Event $event;
    private User $admin;
    private Player $incoming;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create(['entryFee' => 0, 'start_date' => '2026-12-01']);
        $category = Category::factory()->create(['name' => 'u/13 Boys']);
        $entry = CategoryEvent::factory()->create(['event_id' => $this->event->id, 'category_id' => $category->id, 'entry_fee' => 0]);
        $region = TeamRegion::create(['region_name' => 'North', 'short_name' => 'N', 'region_fee' => 0]);
        $this->team = Team::factory()->create(['category_event_id' => $entry->id, 'region_id' => $region->id]);
        $this->away = Team::factory()->create(['category_event_id' => $entry->id, 'region_id' => $region->id]);
        $this->incoming = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-01-01', 'name' => 'New', 'surname' => 'Player']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    private function source(string $type): array
    {
        if ($type === 'profile') {
            $player = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-02-01']);
            $member = TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $player->id, 'pay_status' => 1]);
            return [$player->id, $member];
        }
        $member = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Old', 'surname' => 'Imported', 'pay_status' => 1]);
        return [$member->id, $member];
    }

    private function fixture(string $type, int $old, int $round, int $status = 0): TeamFixture
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id, 'locked' => false, 'published' => false]);
        $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => $round, 'tie_nr' => 1, 'home_team_id' => $this->team->id, 'away_team_id' => $this->away->id, 'status' => 'draft']);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'round_nr' => $round, 'tie_nr' => 1, 'match_nr' => 1, 'numSets' => 3, 'fixture_type' => 1, 'match_status' => $status]);
        TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1'.($type === 'profile' ? '_id' : '_no_profile_id') => $old]);
        return $fixture;
    }

    private function input(string $oldType, int $oldId, string $newType): array
    {
        $data = ['old_type' => $oldType, 'old_id' => $oldId, 'new_type' => $newType, 'scope' => 'next', 'reason' => 'Player unavailable'];
        return $newType === 'profile' ? $data + ['new_id' => $this->incoming->id]
            : $data + ['name' => 'New', 'surname' => 'Imported', 'date_of_birth' => '2014-04-01', 'gender' => 'male'];
    }

    private function preview(array $input)
    {
        return $this->actingAs($this->admin)->postJson(route('backend.team-substitutions.preview', $this->team), $input);
    }

    private function apply(array $input, array $preview, string $key = 'replacement-key-001')
    {
        return $this->actingAs($this->admin)->postJson(route('backend.team-substitutions.store', $this->team), $input + ['fingerprint' => $preview['fingerprint'], 'request_key' => $key]);
    }

    public static function transitions(): array
    {
        return [['profile', 'profile'], ['profile', 'imported'], ['imported', 'profile'], ['imported', 'imported']];
    }

    #[DataProvider('transitions')]
    public function test_identity_transitions_preserve_original_roster_and_completed_fixtures(string $oldType, string $newType): void
    {
        [$oldId, $source] = $this->source($oldType);
        $original = $source->fresh()->getAttributes();
        $past = $this->fixture($oldType, $oldId, 1, 1);
        $future = $this->fixture($oldType, $oldId, 2);
        $input = $this->input($oldType, $oldId, $newType);
        $preview = $this->preview($input)->assertOk()->assertJsonPath('selected_ids.0', $future->id)->json();
        $created = $this->apply($input, $preview)->assertOk()->json();
        $this->apply($input, $preview)->assertOk()->assertJsonPath('id', $created['id']);
        $this->get(route('backend.team-substitutions.show', $this->team))->assertOk()
            ->assertSee('Recorded replacements')->assertSee($preview['new_name'])->assertSee('Player unavailable');
        $this->assertDatabaseCount('team_substitutions', 1);
        $this->assertSame($original, $source->fresh()->getAttributes());
        $column = 'team1'.($oldType === 'profile' ? '_id' : '_no_profile_id');
        $this->assertSame($oldId, (int) $past->fixturePlayers()->first()->$column);
        $changed = $future->fixturePlayers()->first();
        $this->assertSame(1, $changed->participant_snapshot[1]['rank']);
        $this->assertSame($this->team->id, $changed->participant_snapshot[1]['source_team_id']);
        $active = app(TeamDrawSideResolver::class)->activeRoster($this->team->fresh());
        $this->assertSame(1, $active->team_players->count() + $active->team_players_no_profile->count());
        if ($newType === 'profile') $this->assertSame($this->incoming->id, $changed->team1_id);
        else $this->assertNull(NoProfileTeamPlayer::find($changed->team1_no_profile_id)->team_id);
    }

    public function test_stale_preview_and_protected_states_do_not_change_participants(): void
    {
        [$id] = $this->source('profile');
        $pending = $this->fixture('profile', $id, 1);
        $pastScheduled = $this->fixture('profile', $id, 2);
        $pastScheduled->update(['scheduled_at' => now()->subHour()]);
        $live = $this->fixture('profile', $id, 3, 2);
        $input = $this->input('profile', $id, 'profile');
        $preview = $this->preview($input)->assertOk()->assertJsonCount(1, 'selected_ids')->json();
        $pending->update(['match_status' => 2]);
        $this->apply($input, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('team_substitutions', 0);
        $this->assertSame($id, $live->fixturePlayers()->first()->team1_id);
    }

    public function test_event_scope_and_payment_gate_are_enforced(): void
    {
        [$id] = $this->source('profile');
        $input = $this->input('profile', $id, 'profile');
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->postJson(route('backend.team-substitutions.preview', $this->team), $input)->assertForbidden();
        $this->team->regions->update(['region_fee' => 25]);
        $this->preview($input)->assertUnprocessable();
        $this->assertDatabaseCount('team_substitutions', 0);
    }

    public function test_round_replacements_preserve_earlier_assignments_and_chain_source_slot_history(): void
    {
        [$id] = $this->source('profile');
        $fixtures = collect([1, 2, 3, 4])->map(fn ($round) => $this->fixture('profile', $id, $round));
        $input = $this->input('profile', $id, 'profile') + ['from_round' => 3];
        $input['scope'] = 'round';
        $this->apply($input, $this->preview($input)->assertOk()->assertJsonCount(2, 'selected_ids')->json())->assertOk();
        $this->assertSame($id, $fixtures[0]->fixturePlayers()->first()->team1_id);
        $this->assertSame($this->incoming->id, $fixtures[2]->fixturePlayers()->first()->team1_id);
        $firstReplacement = $this->incoming;
        $this->incoming = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-05-01']);
        $next = $this->input('profile', $firstReplacement->id, 'profile') + ['from_round' => 4];
        $next['scope'] = 'round';
        $this->apply($next, $this->preview($next)->assertOk()->json(), 'replacement-key-002')->assertOk();
        $this->assertSame($firstReplacement->id, $fixtures[2]->fixturePlayers()->first()->team1_id);
        $this->assertSame($this->incoming->id, $fixtures[3]->fixturePlayers()->first()->team1_id);
        $resolver = app(TeamDrawSideResolver::class);
        $this->assertSame($id, $resolver->side($fixtures[0]->draw, $this->team->fresh(), 1, $fixtures[0]->id)->team_players->first()->player_id);
        $this->assertSame($firstReplacement->id, $resolver->side($fixtures[2]->draw, $this->team->fresh(), 3, $fixtures[2]->id)->team_players->first()->player_id);
        $newDraw = Draw::factory()->create(['event_id' => $this->event->id]);
        $this->assertSame($this->incoming->id, $resolver->side($newDraw, $this->team->fresh(), 1)->team_players->first()->player_id);
    }

    public function test_specific_stand_in_keeps_roster_and_other_matches_and_rejects_empty_or_changed_retry(): void
    {
        [$id] = $this->source('profile');
        $first = $this->fixture('profile', $id, 1);
        $second = $this->fixture('profile', $id, 2);
        $input = $this->input('profile', $id, 'profile');
        $input['scope'] = 'specific';
        $input['fixture_ids'] = [];
        $this->apply($input, $this->preview($input)->assertOk()->json())->assertUnprocessable();
        $input['fixture_ids'] = [$second->id];
        $preview = $this->preview($input)->assertOk()->json();
        $this->apply($input, $preview)->assertOk();
        $this->assertSame($id, $first->fixturePlayers()->first()->team1_id);
        $this->assertSame($id, app(TeamDrawSideResolver::class)->activeRoster($this->team->fresh())->team_players->first()->player_id);
        $this->assertSame($this->incoming->id, app(TeamDrawSideResolver::class)->side($second->draw, $this->team->fresh(), 2, $second->id)->team_players->first()->player_id);
        $input['reason'] = 'Changed request payload';
        $this->apply($input, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('team_substitutions', 1);
    }

    public function test_published_future_fixture_change_is_audited_and_destructive_regeneration_is_blocked(): void
    {
        [$id] = $this->source('profile');
        $fixture = $this->fixture('profile', $id, 1);
        $fixture->draw->update(['published' => true]);
        $fixture->teamTie->update(['status' => 'published', 'published_at' => now()]);
        $input = $this->input('profile', $id, 'profile');
        $this->apply($input, $this->preview($input)->assertOk()->json())->assertOk();
        $this->assertTrue((bool) $fixture->draw->fresh()->published);
        $this->assertSame('published', $fixture->teamTie->fresh()->status);
        try {
            app(\App\Services\TeamDrawMutationGuard::class)->destructive($fixture->draw->fresh());
            $this->fail('Expected destructive guard');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('team_fixtures', 1);
    }

    public function test_scheduled_time_crossing_and_legacy_bypasses_require_fresh_review(): void
    {
        [$id, $member] = $this->source('profile');
        $fixture = $this->fixture('profile', $id, 1);
        $fixture->update(['scheduled_at' => now()->addMinute()]);
        $input = $this->input('profile', $id, 'profile');
        $preview = $this->preview($input)->assertOk()->json();
        $this->travel(2)->minutes();
        $this->apply($input, $preview)->assertUnprocessable();
        $this->actingAs($this->admin)->postJson('/backend/team/replacePlayer', ['team_id' => $this->team->id, 'pivot_id' => $member->id, 'player_id' => $this->incoming->id])->assertStatus(409);
        $this->actingAs($this->admin)->postJson(route('backend.team-fixtures.replacePlayer'), ['event_id' => $this->event->id, 'old_id' => 'p_'.$id, 'new_id' => 'p_'.$this->incoming->id])->assertStatus(409);
        $this->assertDatabaseCount('team_substitutions', 0);
    }

    public function test_unverified_paid_flags_and_category_zero_cannot_bypass_region_and_event_fees(): void
    {
        [$id] = $this->source('profile');
        $this->event->update(['entryFee' => 30]);
        $this->team->regions->update(['region_fee' => 20]);
        $order = \App\Models\TeamPaymentOrder::create(['event_id' => $this->event->id, 'team_id' => $this->team->id, 'player_id' => $this->incoming->id,
            'user_id' => $this->admin->id, 'pay_status' => true, 'total_amount' => 30, 'wallet_reserved' => 0, 'payfast_amount_due' => 30, 'wallet_debited' => false, 'payfast_paid' => false]);
        $input = $this->input('profile', $id, 'profile');
        $this->preview($input)->assertUnprocessable();
        $order->update(['total_amount' => 50, 'payfast_amount_due' => 50, 'payfast_paid' => true, 'payfast_pf_payment_id' => 'not-a-verified-receipt']);
        $original = $order->fresh()->getAttributes();
        $this->preview($input)->assertUnprocessable();
        $this->assertSame($original, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_substitutions', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_global_replacement_resolves_all_existing_source_slot_identities_after_round_changes(): void
    {
        [$originalId, $originalMember] = $this->source('profile');
        $fixtures = collect([1, 2, 3])->map(fn ($round) => $this->fixture('profile', $originalId, $round));
        $first = $this->incoming;
        $input = $this->input('profile', $originalId, 'profile') + ['from_round' => 3];
        $input['scope'] = 'round';
        $this->apply($input, $this->preview($input)->assertOk()->json())->assertOk();
        $this->incoming = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-06-01']);
        $next = $this->input('profile', $first->id, 'profile');
        $preview = $this->preview($next)->assertOk()->assertJsonCount(3, 'selected_ids')->json();
        $this->apply($next, $preview, 'replacement-key-003')->assertOk();
        foreach ($fixtures as $fixture) $this->assertSame($this->incoming->id, $fixture->fixturePlayers()->first()->team1_id);
        $current = $this->incoming;
        $this->incoming = Player::find($originalId);
        $return = $this->input('profile', $current->id, 'profile');
        $this->apply($return, $this->preview($return)->assertOk()->json(), 'replacement-key-004')->assertOk();
        $this->assertSame(1, $originalMember->fresh()->pay_status);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseCount('team_substitutions', 3);
    }

    public function test_specific_stand_in_cannot_be_reused_for_a_second_source_slot(): void
    {
        [$originalId] = $this->source('profile');
        $partner = Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2014-03-01']);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => $partner->id]);
        $fixture = $this->fixture('profile', $originalId, 1);
        $fixture->update(['fixture_type' => 2]);
        TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 2, 'team1_id' => $partner->id]);
        $first = $this->input('profile', $partner->id, 'profile');
        $first['scope'] = 'specific';
        $first['fixture_ids'] = [$fixture->id];
        $this->apply($first, $this->preview($first)->assertOk()->json())->assertOk();
        $second = $this->input('profile', $originalId, 'profile');
        $second['scope'] = 'specific';
        $second['fixture_ids'] = [$fixture->id];
        $this->preview($second)->assertUnprocessable();
        $this->assertSame($originalId, $fixture->fixturePlayers()->where('slot_no', 1)->first()->team1_id);
        $this->assertSame($this->incoming->id, $fixture->fixturePlayers()->where('slot_no', 2)->first()->team1_id);
        $this->assertDatabaseCount('team_substitutions', 1);
        try {
            app(\App\Services\TeamDrawMutationGuard::class)->fixture($fixture->fresh());
            $this->fail('Legacy structural changes must be blocked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_mixed_replacement_preserves_partner_opponents_and_completed_snapshot(): void
    {
        [$originalId] = $this->source('profile');
        $girlsEntry = CategoryEvent::factory()->create(['event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'u/13 Girls'])->id, 'entry_fee' => 0]);
        $girls = Team::factory()->create(['category_event_id' => $girlsEntry->id, 'region_id' => $this->team->region_id]);
        $awayGirls = Team::factory()->create(['category_event_id' => $girlsEntry->id, 'region_id' => $this->away->region_id]);
        $partner = Player::factory()->create(['gender' => 2]);
        $opponent = Player::factory()->create(['gender' => 1]);
        $opponentPartner = Player::factory()->create(['gender' => 2]);
        foreach ([[$girls, $partner], [$this->away, $opponent], [$awayGirls, $opponentPartner]] as [$team, $player]) {
            TeamPlayer::create(['team_id' => $team->id, 'rank' => 1, 'player_id' => $player->id]);
        }
        $fixtures = collect([0, 1])->map(function ($status) use ($originalId, $girlsEntry, $girls, $awayGirls, $partner, $opponent, $opponentPartner) {
            $fixture = $this->fixture('profile', $originalId, $status + 1, $status);
            $fixture->draw->forceFill(['team_draw_selection' => ['category_ids' => [$this->team->category_event_id, $girlsEntry->id],
                'mixed_sides' => [$this->team->id => ['boys' => $this->team->id, 'girls' => $girls->id, 'region_id' => $this->team->region_id],
                    $this->away->id => ['boys' => $this->away->id, 'girls' => $awayGirls->id, 'region_id' => $this->away->region_id]]],
                'team_format_snapshot' => ['rubbers' => [], 'allow_player_reuse' => false]])->save();
            $fixture->update(['fixture_type' => 3, 'rubber_code' => 'mixed_doubles', 'player_count_per_team' => 2, 'gender_rule' => 'mixed']);
            $fixture->fixturePlayers()->first()->update(['team2_id' => $opponent->id]);
            TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 2, 'team1_id' => $partner->id, 'team2_id' => $opponentPartner->id]);
            return $fixture;
        });
        $oldName = Player::find($originalId)->full_name;
        $input = $this->input('profile', $originalId, 'profile');
        $this->apply($input, $this->preview($input)->assertOk()->json())->assertOk();
        foreach ($fixtures as $fixture) {
            $rows = $fixture->fixturePlayers()->orderBy('slot_no')->get();
            $this->assertSame($opponent->id, $rows[0]->team2_id);
            $this->assertSame($partner->id, $rows[1]->team1_id);
            $this->assertSame($opponentPartner->id, $rows[1]->team2_id);
            app(\App\Services\TeamTieValidationService::class)->assertTieComplete($fixture->teamTie->fresh());
        }
        $past = $fixtures->last();
        Player::find($originalId)->update(['name' => 'Renamed historical profile']);
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare(collect([$past->fresh()]));
        $presented = $past->fresh();
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare(collect([$presented]));
        $this->assertSame($oldName, $presented->lineup_display['home']['players'][0]['name']);
        $this->assertSame(1, $presented->lineup_display['home']['players'][0]['rank']);
    }

    public function test_returning_player_can_use_original_verified_coverage_without_financial_mutation(): void
    {
        [$originalId, $originalMember] = $this->source('profile');
        $fixture = $this->fixture('profile', $originalId, 1);
        $input = $this->input('profile', $originalId, 'profile');
        $this->apply($input, $this->preview($input)->assertOk()->json())->assertOk();
        $replacement = $this->incoming;
        $this->event->update(['entryFee' => 50]);
        $order = \App\Models\TeamPaymentOrder::create(['event_id' => $this->event->id, 'team_id' => $this->team->id,
            'player_id' => $originalId, 'user_id' => $this->admin->id, 'pay_status' => true, 'total_amount' => 50,
            'wallet_reserved' => 0, 'payfast_amount_due' => 50, 'wallet_debited' => false,
            'payfast_paid' => true, 'payfast_pf_payment_id' => 'verified-return-receipt']);
        $receipt = new \App\Models\Transaction;
        $receipt->forceFill(['custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'pf_payment_id' => 'verified-return-receipt',
            'amount_gross' => 50, 'custom_int2' => $originalId, 'custom_int3' => $this->event->id, 'custom_int4' => $this->admin->id])->save();
        $orderBefore = $order->fresh()->getAttributes();
        $receiptBefore = $receipt->fresh()->getAttributes();
        $this->incoming = Player::find($originalId);
        $return = $this->input('profile', $replacement->id, 'profile');
        $this->apply($return, $this->preview($return)->assertOk()->json(), 'replacement-return-paid')->assertOk();
        $this->assertSame($originalId, $fixture->fixturePlayers()->first()->team1_id);
        $this->assertSame($orderBefore, $order->fresh()->getAttributes());
        $this->assertSame($receiptBefore, $receipt->fresh()->getAttributes());
        $this->assertSame(1, $originalMember->fresh()->pay_status);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_stale_score_and_start_pages_cannot_attribute_play_to_replacement(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->admin->assignRole('super-user');
        [$oldId] = $this->source('profile');
        $fixture = $this->fixture('profile', $oldId, 1);
        $oldRevision = app(\App\Services\TeamParticipantHistoryService::class)->revision($fixture);
        $input = $this->input('profile', $oldId, 'profile');
        $this->apply($input, $this->preview($input)->assertOk()->json())->assertOk();
        $scores = ['set1_home' => 6, 'set1_away' => 2];
        $scoreUrl = route('backend.team-fixtures.insertScore', $fixture);
        $startUrl = route('frontend.scoring.team-fixtures.playing', [$this->event, $fixture]);
        $this->postJson($scoreUrl, $scores)->assertStatus(409);
        $this->postJson($scoreUrl, $scores + ['participant_revision' => $oldRevision])->assertStatus(409);
        $this->postJson($startUrl, ['playing' => true, 'participant_revision' => $oldRevision])->assertStatus(409);
        $this->assertDatabaseCount('team_fixture_results', 0);
        $this->assertSame(0, (int) $fixture->fresh()->match_status);
        $revision = app(\App\Services\TeamParticipantHistoryService::class)->revision($fixture->fresh());
        $this->postJson($startUrl, ['playing' => true, 'participant_revision' => $revision])->assertOk();
        $this->postJson($scoreUrl, $scores + ['participant_revision' => $revision])->assertRedirect();
        $this->assertDatabaseCount('team_fixture_results', 1);
    }

    public function test_imported_identity_details_cannot_create_same_person_again(): void
    {
        [$oldId] = $this->source('imported');
        $this->fixture('imported', $oldId, 1);
        NoProfileTeamPlayer::find($oldId)->update(['date_of_birth' => '2014-02-01']);
        $input = $this->input('imported', $oldId, 'imported');
        $input['name'] = '  old ';
        $input['surname'] = 'IMPORTED';
        $input['date_of_birth'] = '2014-02-01';
        $this->preview($input)->assertUnprocessable();
        $this->assertDatabaseCount('team_substitutions', 0);
        $this->assertDatabaseCount('no_profile_team_players', 1);
    }
}
