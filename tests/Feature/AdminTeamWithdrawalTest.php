<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, User, WalletTransaction};
use App\Services\AdminTeamWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event as Events};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminTeamWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Events::fake([\App\Events\RefundCompleted::class]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create();
        $event = Event::factory()->create(['withdrawal_deadline' => now()->addDays(3)]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => 0]);
        $player = Player::factory()->create();
        $slot = TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $order = TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id,
            'user_id' => $payer->id, 'total_amount' => 285, 'wallet_reserved' => 285, 'payfast_amount_due' => 0, 'wallet_debited' => true, 'pay_status' => true]);
        $wallet = $payer->wallet()->firstOrCreate();
        WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'debit', 'amount' => 285, 'source_type' => 'team_registration_wallet_payment', 'source_id' => $order->id]);
        $this->actingAs($actor);
        return compact('actor', 'payer', 'event', 'team', 'player', 'slot', 'order', 'wallet');
    }

    private function payload(array $s): array
    {
        $review = app(AdminTeamWithdrawalService::class)->review($s['event'], $s['team'], $s['slot'], $s['actor']);
        return ['order_id' => $s['order']->id, 'fingerprint' => $review['fingerprint'], 'reason' => 'Player requested withdrawal', 'confirm' => 1];
    }

    private function competitionScenario(bool $imported = false): array
    {
        $s = $this->scenario();
        $type = \App\Models\EventType::forceCreate(['name' => 'Open team event', 'type' => \App\Models\EventType::TEAM]);
        $s['event']->update(['eventType' => $type->id, 'signUp' => true, 'deadline' => 0, 'start_date' => now()->addWeek()]);
        $s['team']->update(['noProfile' => $imported, 'num_team_members' => 1]);
        if ($imported) $s['imported'] = \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id,
            'rank' => 1, 'name' => $s['player']->name, 'surname' => $s['player']->surname, 'player_profile' => $s['player']->id, 'pay_status' => 1]);
        $opponent = Team::factory()->create(['category_event_id' => $s['team']->category_event_id, 'noProfile' => false, 'num_team_members' => 1]);
        TeamPlayer::create(['team_id' => $opponent->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 0]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $s['event']->id, 'locked' => false, 'published' => false]);
        $draw->forceFill(['team_category_id' => 1, 'team_draw_selection' => ['category_ids' => [$s['team']->category_event_id], 'rubber_code' => 'singles', 'players_per_team' => 1],
            'team_format_snapshot' => ['name' => 'Singles', 'rubbers' => [['sequence' => 1, 'rubber_code' => 'singles', 'name' => 'Singles 1',
                'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [1]]]]])->save();
        app(\App\Services\TeamDrawAdaptationService::class)->adaptDraw($draw, null, true, true);
        $fixture = $draw->fixtures()->firstOrFail();
        if ($imported) {
            // Historical fixtures may retain an imported identity captured before a later profile claim.
            $assignment = $fixture->fixturePlayers()->firstOrFail();
            $snapshot = $assignment->participant_snapshot;
            $snapshot[1]['profile_id'] = null;
            $snapshot[1]['imported_id'] = (int) $s['imported']->id;
            $snapshot[1]['linked_profile_id'] = (int) $s['player']->id;
            $assignment->forceFill(['team1_id' => null, 'team1_no_profile_id' => $s['imported']->id, 'participant_snapshot' => $snapshot])->save();
        }
        return $s + compact('draw', 'fixture');
    }

    public function test_open_paid_profile_replacement_preserves_started_participation_and_refunds_exactly_once(): void
    {
        foreach ([1] as $status) {
            $s = $this->competitionScenario();
            $s['fixture']->update(['match_status' => $status]);
            if ($status === 2) $s['fixture']->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
            $before = $s['fixture']->fresh()->getAttributes();
            $players = $s['fixture']->fixturePlayers()->get()->map->getAttributes()->all();
            $results = $s['fixture']->fixtureResults()->get()->map->getAttributes()->all();
            $payload = $this->replacementPayload($s);
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
            $this->assertSame($before, $s['fixture']->fresh()->getAttributes());
            $this->assertSame($players, $s['fixture']->fixturePlayers()->get()->map->getAttributes()->all());
            $this->assertSame($results, $s['fixture']->fixtureResults()->get()->map->getAttributes()->all());
            $this->assertEquals($s['player']->id, $s['fixture']->fixturePlayers()->firstOrFail()->team1_id);
            $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => $payload['player_id'], 'pay_status' => 0]);
            $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'refund_status' => 'completed', 'refund_net' => 256.50, 'user_id' => $s['payer']->id]);
            $this->assertSame(1, WalletTransaction::where('wallet_id', $s['wallet']->id)->where('type', 'credit')->count());
        }
    }

    public function test_imported_started_closed_and_scored_paid_replacements_are_rejected_without_refund_effects(): void
    {
        foreach (['imported', 'closed', 'scored'] as $case) {
            $imported = $case === 'imported';
            $s = $this->competitionScenario($imported);
            $s['fixture']->update(['match_status' => 1]);
            if ($case === 'closed') $s['event']->update(['signUp' => false]);
            if ($case === 'scored') $s['fixture']->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
            $players = $s['fixture']->fixturePlayers()->get()->map->getAttributes()->all();
            $payload = $this->replacementPayload($s);
            if ($imported) $payload['slot_id'] = $s['imported']->id;
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
            $this->assertSame($players, $s['fixture']->fixturePlayers()->get()->map->getAttributes()->all());
            $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => $s['player']->id, 'pay_status' => 1]);
            $this->assertNull($s['order']->fresh()->withdrawn_at);
            $this->assertSame(0, WalletTransaction::where('wallet_id', $s['wallet']->id)->where('type', 'credit')->count());
        }
    }

    private function replacementPayload(array $s, string $action = 'refund'): array
    {
        return ['event_id' => $s['event']->id, 'team_id' => $s['team']->id, 'slot_id' => $s['slot']->id,
            'expected_rank' => 1, 'expected_player_id' => $s['player']->id,
            'player_id' => Player::factory()->create()->id, 'refund_action' => $action, 'reason' => 'Player replaced at their request'];
    }

    public function test_paid_roster_replacement_refunds_original_payer_once_and_replacement_starts_unpaid(): void
    {
        $s = $this->scenario(); $payload = $this->replacementPayload($s);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => $payload['player_id'], 'pay_status' => 0, 'rank' => 1]);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'player_id' => $s['player']->id, 'user_id' => $s['payer']->id, 'pay_status' => 1, 'refund_status' => 'completed', 'refund_net' => 256.50]);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 256.50, 'source_id' => $s['order']->id]);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('team_players', 1);
    }

    public function test_refund_can_fund_replacement_without_spending_other_wallet_funds(): void
    {
        foreach ([256.50, 200, 285] as $fee) {
            $s = $this->scenario();
            $s['event']->update(['entryFee' => $fee]);
            if ($fee === 200) {
                $s['team']->update(['noProfile' => 1]);
                $raw = \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'rank' => 1,
                    'player_profile' => $s['player']->id, 'name' => $s['player']->name, 'surname' => $s['player']->surname, 'pay_status' => 0]);
            }
            WalletTransaction::create(['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 1000, 'source_type' => 'test', 'source_id' => 1]);
            $payload = $this->replacementPayload($s, 'replacement');
            if ($fee === 200) $payload['slot_id'] = $raw->id;
            $response = $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
            $order = TeamPaymentOrder::where('player_id', $payload['player_id'])->sole();
            $applied = min(256.50, $fee);
            $this->assertSame((int) $s['payer']->id, (int) $order->user_id);
            $this->assertSame($fee > 256.50 ? false : true, $order->pay_status);
            $this->assertEquals($applied, $order->wallet_reserved);
            $this->assertEquals(max(0, $fee - 256.50), $order->payfast_amount_due);
            $this->assertEquals($fee <= 256.50 ? $applied : 0, WalletTransaction::where('source_type', 'team_registration_wallet_payment')->where('source_id', $order->id)->sum('amount'));
            $this->assertSame((int) $s['player']->id, (int) $s['order']->fresh()->player_id);
            $this->assertTrue($s['order']->fresh()->pay_status);
            $this->assertEquals(715 + 256.50 - ($fee <= 256.50 ? $applied : 0), $s['wallet']->fresh()->balance);
            $this->assertSame($fee <= 256.50 ? 4 : 3, WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $this->assertSame(1, WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $s['order']->id)->count());
            $audit = \Spatie\Activitylog\Models\Activity::where('subject_type', TeamPaymentOrder::class)->where('subject_id', $s['order']->id)
                ->where('description', 'applied withdrawal refund to replacement checkout')->sole();
            $this->assertEquals($order->id, $audit->properties['replacement_order_id']);
            $this->assertEquals($applied, $audit->properties['amount_applied']);
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertStatus($fee === 200 ? 404 : 422);
            $this->assertSame(1, TeamPaymentOrder::where('player_id', $payload['player_id'])->count());
            if ($fee > 256.50) {
                $response->assertJsonPath('message', 'Player changed. R256.50 of the refund is reserved. The original payer must complete the remaining R28.50 through checkout within five minutes or restart. The replacement remains unpaid until payment completes.');
                $this->actingAs($s['actor'])->get(route('team.checkout', $order))->assertForbidden();
                $this->actingAs($s['payer'])->get(route('team.checkout', $order))->assertOk()->assertViewHas('walletReserved', 256.50);
                $this->travel(6)->minutes();
                $this->assertTrue(app(\App\Domain\Payments\Services\TeamPaymentService::class)->expireUnpaidCheckout($order));
                $this->assertEquals(0, $order->fresh()->wallet_reserved);
                $this->assertFalse($order->fresh()->wallet_debited);
                $this->assertSame(1, WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $s['order']->id)->count());
                $this->travelBack();
            }
        }
    }

    public function test_refund_replacement_rejects_late_unauthorized_and_cross_event_requests_atomically(): void
    {
        $s = $this->scenario(); $payload = $this->replacementPayload($s, 'replacement');
        $this->postJson(route('backend.team.changePlayer'), array_replace($payload, ['event_id' => Event::factory()->create()->id]))->assertNotFound();
        $admin = User::factory()->create();
        \App\Models\EventAdmin::create(['event_id' => $s['event']->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('backend.team.changePlayer'), $payload)->assertForbidden();
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $this->actingAs($s['actor'])->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_partial_refund_replacement_rolls_back_when_checkout_is_closed(): void
    {
        $s = $this->scenario();
        $s['event']->update(['entryFee' => 285, 'signUp' => false]);
        $this->postJson(route('backend.team.changePlayer'), $this->replacementPayload($s, 'replacement'))->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertEquals($s['player']->id, $s['slot']->fresh()->player_id);
    }

    public function test_refund_replacement_cannot_bypass_managed_selection_acceptance(): void
    {
        $s = $this->scenario();
        $import = \App\Models\TeamSelectionImport::create(['source_id' => 1, 'event_id' => $s['event']->id, 'region_id' => 1,
            'series_id' => 1, 'ranking_run_id' => 'test', 'status' => 'sent']);
        \App\Models\TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $s['event']->id, 'region_id' => 1,
            'team_id' => $s['team']->id, 'player_id' => $s['player']->id, 'ranking_list_id' => 1, 'ranking_position' => 1,
            'queue_position' => 1, 'roster_rank' => 1, 'status' => 'paid_confirmed', 'order_id' => $s['order']->id]);
        $this->postJson(route('backend.team.changePlayer'), $this->replacementPayload($s, 'replacement'))->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertEquals($s['player']->id, $s['slot']->fresh()->player_id);
    }

    public function test_paid_imported_replacement_detects_paid_mirror_and_records_waiver_without_money_moving(): void
    {
        $s = $this->scenario(); $payload = $this->replacementPayload($s, 'waive');
        $s['team']->update(['noProfile' => 1]);
        $raw = \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'name' => $s['player']->name, 'surname' => $s['player']->surname, 'player_profile' => $s['player']->id, 'rank' => 1, 'pay_status' => 0]);
        $payload['slot_id'] = $raw->id;
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertNotFound();
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => $payload['player_id'], 'pay_status' => 0]);
        $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $s['team']->id, 'rank' => 1, 'player_profile' => $payload['player_id'], 'pay_status' => 0]);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1, 'user_id' => $s['payer']->id, 'player_id' => $s['player']->id, 'refund_status' => 'waived', 'refund_waived_by' => $s['actor']->id, 'refund_waiver_reason' => $payload['reason'], 'refunded_at' => null]);
        $this->assertNotNull($s['order']->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseCount('no_profile_team_players', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
        Events::assertNotDispatched(\App\Events\RefundCompleted::class);
    }

    public function test_paid_replacement_rejects_missing_decision_cross_event_and_invalid_target_atomically(): void
    {
        $s = $this->scenario(); $payload = $this->replacementPayload($s);
        foreach ([['refund_action' => null], ['reason' => ' '], ['event_id' => Event::factory()->create()->id], ['player_id' => $s['player']->id]] as $bad) {
            $this->postJson(route('backend.team.changePlayer'), array_replace($payload, $bad))->assertStatus(isset($bad['event_id']) ? 404 : 422);
        }
        $otherTeam = Team::factory()->create(['category_event_id' => $s['team']->category_event_id, 'noProfile' => 0]);
        app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($otherTeam, Player::findOrFail($payload['player_id']), 1);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertSame('not_refunded', $s['order']->fresh()->refund_status);
        $this->assertSame($s['player']->id, (int) $s['slot']->fresh()->player_id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_late_replacement_requires_explicit_decision_and_event_admin_cannot_make_financial_decision(): void
    {
        $s = $this->scenario(); $payload = $this->replacementPayload($s);
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $admin = User::factory()->create();
        \App\Models\EventAdmin::create(['event_id' => $s['event']->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('backend.team.changePlayer'), $payload)->assertForbidden();
        $this->actingAs($s['actor'])->postJson(route('backend.team.changePlayer'), array_replace($payload, ['refund_action' => 'waive']))->assertOk();
        $this->assertSame('waived', $s['order']->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_late_replacement_override_refunds_and_funds_only_original_payer_exactly_once(): void
    {
        foreach (['refund', 'replacement'] as $action) {
            $s = $this->scenario();
            WalletTransaction::create(['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 285,
                'source_type' => 'test_opening_funds', 'source_id' => $s['order']->id]);
            $s['event']->update(['withdrawal_deadline' => now()->subDay(), 'entryFee' => 256.50]);
            $payload = $this->replacementPayload($s, $action) + ['deadline_override' => 1];
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
            $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1,
                'player_id' => $s['player']->id, 'user_id' => $s['payer']->id,
                'refund_status' => 'completed', 'refund_fee' => 28.50, 'refund_net' => 256.50]);
            $this->assertSame(1, WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $s['order']->id)->count());
            $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit',
                'amount' => 256.50, 'source_type' => 'admin_team_withdrawal_refund', 'source_id' => $s['order']->id]);
            $orders = TeamPaymentOrder::where('team_id', $s['team']->id)->get();
            $this->assertCount($action === 'replacement' ? 2 : 1, $orders);
            $this->assertSame($action === 'replacement' ? 4 : 3, WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => $payload['player_id'],
                'pay_status' => $action === 'replacement' ? 1 : 0]);
            if ($action === 'replacement') {
                $replacement = $orders->firstWhere('player_id', $payload['player_id']);
                $this->assertSame((int) $s['payer']->id, (int) $replacement->user_id);
                $this->assertEquals(256.50, $replacement->wallet_reserved);
                $this->assertEquals(0, $replacement->payfast_amount_due);
                $this->assertTrue($replacement->wallet_debited);
            }
            $audit = \Spatie\Activitylog\Models\Activity::where('subject_id', $s['order']->id)
                ->where('description', 'super user authorized team withdrawal refund after deadline')->sole();
            $this->assertTrue($audit->properties['deadline_override']);
            $this->assertSame($payload['reason'], $audit->properties['reason']);
            $this->assertSame((int) $s['actor']->id, (int) $audit->causer_id);
        }
    }

    public function test_replacement_deadline_override_rejects_invalid_or_unauthorized_requests_atomically(): void
    {
        $s = $this->scenario();
        $payload = $this->replacementPayload($s, 'replacement') + ['deadline_override' => 1];
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable(); // Not late.
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        foreach ([['deadline_override' => 0], ['deadline_override' => 'yes'], ['reason' => ' '], ['refund_action' => 'waive']] as $bad) {
            $this->postJson(route('backend.team.changePlayer'), array_replace($payload, $bad))->assertUnprocessable();
        }
        $this->postJson(route('backend.team.changePlayer'), array_replace($payload, ['event_id' => Event::factory()->create()->id]))->assertNotFound();
        $admin = User::factory()->create();
        \App\Models\EventAdmin::create(['event_id' => $s['event']->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('backend.team.changePlayer'), $payload)->assertForbidden();
        $this->actingAs($s['actor']);
        $otherTeam = Team::factory()->create(['category_event_id' => $s['team']->category_event_id, 'noProfile' => 0]);
        app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($otherTeam, Player::findOrFail($payload['player_id']), 1);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertSame('not_refunded', $s['order']->fresh()->refund_status);
        $this->assertSame($s['player']->id, (int) $s['slot']->fresh()->player_id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_replacement_form_offers_override_only_for_late_paid_super_user(): void
    {
        $s = $this->scenario();
        $render = fn () => view('backend.adminPage.admin_show._roster-actions', [
            'event' => $s['event'], 'team' => $s['team'], 'slot' => $s['slot'], 'name' => 'Test Player',
        ])->render();
        $this->assertStringNotContainsString('name="deadline_override"', $render());
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $this->assertStringContainsString('name="deadline_override"', $render());
        $this->assertStringContainsString('The normal refund fee still applies.', $render());
        $this->actingAs($s['payer']);
        $this->assertStringNotContainsString('name="deadline_override"', $render());
        $this->actingAs($s['actor']);
        $s['slot']->pay_status = 0;
        $this->assertStringNotContainsString('name="deadline_override"', $render());
    }

    public function test_closed_withdrawal_replacement_requires_separate_consent_and_preserves_exact_original_payer_funding(): void
    {
        foreach (['refund', 'replacement', 'waive'] as $action) {
            $s = $this->scenario();
            \App\Models\SiteSetting::set('withdrawal_allowed', '0');
            $s['event']->update(['withdrawal_deadline' => now()->subDay(), 'entryFee' => 256.50]);
            WalletTransaction::create(['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 285,
                'source_type' => 'test_opening_funds', 'source_id' => $s['order']->id]);
            $payload = $this->replacementPayload($s, $action);
            if ($action !== 'waive') $payload['deadline_override'] = 1;
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
            $this->assertNull($s['order']->fresh()->withdrawn_at);
            $payload['withdrawal_override'] = 1;
            if ($action !== 'waive') {
                $this->postJson(route('backend.team.changePlayer'), array_replace($payload, ['deadline_override' => 0]))->assertUnprocessable();
            }
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertOk();
            $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
            $this->assertSame('0', \App\Models\SiteSetting::get('withdrawal_allowed'));
            $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1,
                'user_id' => $s['payer']->id, 'player_id' => $s['player']->id,
                'refund_status' => $action === 'waive' ? 'waived' : 'completed']);
            $this->assertSame($action === 'waive' ? 0 : 1, WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $s['order']->id)->count());
            $this->assertSame($action === 'replacement' ? 4 : ($action === 'refund' ? 3 : 2), WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $this->assertSame($action === 'replacement' ? 2 : 1, TeamPaymentOrder::where('team_id', $s['team']->id)->count());
            if ($action !== 'waive') {
                $this->assertEquals(28.50, $s['order']->fresh()->refund_fee);
                $this->assertEquals(256.50, $s['order']->fresh()->refund_net);
            }
            if ($action === 'replacement') {
                $order = TeamPaymentOrder::where('team_id', $s['team']->id)->where('player_id', $payload['player_id'])->sole();
                $this->assertSame((int) $s['payer']->id, (int) $order->user_id);
                $this->assertTrue($order->pay_status);
                $this->assertEquals(256.50, $order->wallet_reserved);
            }
            $audit = \Spatie\Activitylog\Models\Activity::where('subject_id', $s['order']->id)
                ->where('description', 'super user confirmed team withdrawal')->sole();
            $this->assertTrue($audit->properties['withdrawal_override']);
            $this->assertFalse($audit->properties['withdrawals_allowed']);
            $this->assertSame($payload['reason'], $audit->properties['reason']);
            $this->assertSame((int) $s['actor']->id, (int) $audit->causer_id);
        }
    }

    public function test_closure_override_rejects_invalid_unauthorized_cross_event_and_failed_replacements_atomically(): void
    {
        $s = $this->scenario();
        $payload = $this->replacementPayload($s, 'replacement') + ['withdrawal_override' => 1];
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable(); // Still open.
        \App\Models\SiteSetting::set('withdrawal_allowed', '0');
        foreach ([['withdrawal_override' => 0], ['withdrawal_override' => 'yes'], ['reason' => ' ']] as $bad) {
            $this->postJson(route('backend.team.changePlayer'), array_replace($payload, $bad))->assertUnprocessable();
        }
        $this->postJson(route('backend.team.changePlayer'), array_replace($payload, ['event_id' => Event::factory()->create()->id]))->assertNotFound();
        $admin = User::factory()->create();
        \App\Models\EventAdmin::create(['event_id' => $s['event']->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->postJson(route('backend.team.changePlayer'), $payload)->assertForbidden();
        $this->actingAs($s['actor']);
        $this->postJson($this->url($s), $this->payload($s) + ['withdrawal_override' => 1])->assertUnprocessable();
        $otherTeam = Team::factory()->create(['category_event_id' => $s['team']->category_event_id, 'noProfile' => 0]);
        app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($otherTeam, Player::findOrFail($payload['player_id']), 1);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertSame('not_refunded', $s['order']->fresh()->refund_status);
        $this->assertSame($s['player']->id, (int) $s['slot']->fresh()->player_id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertSame('0', \App\Models\SiteSetting::get('withdrawal_allowed'));
    }

    public function test_closure_override_is_bound_to_fresh_review_reason_and_replay_consent(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s);
        \App\Models\SiteSetting::set('withdrawal_allowed', '0');
        $service = app(AdminTeamWithdrawalService::class);
        $data = $payload + ['withdrawal_override' => 1];
        $reject = function (array $data) use ($service, $s): void {
            try {
                $service->confirm($s['event'], $s['team'], $s['slot'], $s['actor'], $data);
                $this->fail('Expected the withdrawal review to reject the request.');
            } catch (\Illuminate\Validation\ValidationException) {
                $this->assertTrue(true);
            }
        };
        $reject($data); // Old open-withdrawal fingerprint.
        $review = $service->review($s['event'], $s['team'], $s['slot'], $s['actor'], withdrawalOverride: true);
        $data['fingerprint'] = $review['fingerprint'];
        $reject(array_replace($data, ['reason' => ' ']));
        $reject(array_replace($data, ['withdrawal_override' => 0]));
        \App\Models\SiteSetting::set('withdrawal_allowed', '1');
        $reject($data); // Closure was lifted after preview.
        \App\Models\SiteSetting::set('withdrawal_allowed', '0');
        $service->confirm($s['event'], $s['team'], $s['slot'], $s['actor'], $data);
        $service->confirm($s['event'], $s['team'], $s['slot'], $s['actor'], $data);
        $reject(array_replace($data, ['withdrawal_override' => 0]));
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertSame('0', \App\Models\SiteSetting::get('withdrawal_allowed'));
    }

    public function test_closure_override_checkbox_is_only_offered_for_closed_paid_super_user_replacements(): void
    {
        $s = $this->scenario();
        $render = fn () => view('backend.adminPage.admin_show._roster-actions', [
            'event' => $s['event'], 'team' => $s['team'], 'slot' => $s['slot'], 'name' => 'Test Player',
        ])->render();
        $this->assertStringNotContainsString('name="withdrawal_override"', $render());
        \App\Models\SiteSetting::set('withdrawal_allowed', '0');
        $this->assertStringContainsString('name="withdrawal_override"', $render());
        $this->assertStringContainsString('Any refund deadline override must be confirmed separately.', $render());
        $this->actingAs($s['payer']);
        $this->assertStringNotContainsString('name="withdrawal_override"', $render());
        $this->actingAs($s['actor']);
        $s['slot']->pay_status = 0;
        $this->assertStringNotContainsString('name="withdrawal_override"', $render());
    }

    public function test_paid_replacement_rejects_unresolved_handoff_and_waiver_rolls_back_for_duplicate_target(): void
    {
        $s = $this->scenario(); $payload = $this->replacementPayload($s, 'waive');
        DB::table('team_payment_orders')->where('id', $s['order']->id)->update(['payfast_handed_off_at' => now(), 'payfast_amount_due' => 50]);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        DB::table('team_payment_orders')->where('id', $s['order']->id)->update(['payfast_handed_off_at' => null, 'payfast_amount_due' => 0]);
        $otherTeam = Team::factory()->create(['category_event_id' => $s['team']->category_event_id, 'noProfile' => 0]);
        app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($otherTeam, Player::findOrFail($payload['player_id']), 1);
        $this->postJson(route('backend.team.changePlayer'), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertNull($s['order']->fresh()->refund_waived_at);
        $this->assertSame($s['player']->id, (int) $s['slot']->fresh()->player_id);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function url(array $s, string $action = 'store'): string
    {
        return route('backend.team.withdrawal.'.$action, [$s['event'], $s['team'], $s['slot']]);
    }

    public function test_review_is_read_only_and_confirmation_credits_only_original_payer_exactly_once(): void
    {
        $s = $this->scenario();
        $page = $this->get($this->url($s, 'show'))->assertOk()->assertSee('R256.50')->assertSee($s['payer']->name);
        if (getenv('CT_WITHDRAWAL_QA') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/team-withdrawal-qa'));
            file_put_contents(storage_path('app/team-withdrawal-qa/review.html'), $page->getContent());
        }
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $payload = $this->payload($s);
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 256.50, 'source_type' => 'admin_team_withdrawal_refund', 'source_id' => $s['order']->id]);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => 0, 'pay_status' => 0]);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1, 'wallet_debited' => 1, 'refund_gross' => 285, 'refund_fee' => 28.5, 'refund_net' => 256.5, 'refund_status' => 'completed', 'withdrawn_by' => $s['actor']->id]);
        $this->assertSame(1, \Spatie\Activitylog\Models\Activity::where('description', 'super user confirmed team withdrawal')->count());
    }

    public function test_non_super_user_and_cross_event_cannot_review_or_mutate(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s);
        $this->actingAs(User::factory()->create())->get($this->url($s, 'show'))->assertForbidden();
        $this->post($this->url($s), $payload)->assertForbidden();
        $this->actingAs($s['actor']);
        $s['event'] = Event::factory()->create();
        $this->get($this->url($s, 'show'))->assertNotFound();
        $this->post($this->url($s), $payload)->assertNotFound();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
    }

    public function test_stale_player_or_amount_cannot_be_withdrawn(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s);
        DB::table('team_payment_orders')->where('id', $s['order']->id)->update(['total_amount' => 300]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        DB::table('team_payment_orders')->where('id', $s['order']->id)->update(['total_amount' => 285]);
        DB::table('team_players')->where('id', $s['slot']->id)->update(['player_id' => Player::factory()->create()->id]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
    }

    public function test_expired_deadline_requires_fresh_explicit_no_refund_review(): void
    {
        $s = $this->scenario();
        $oldPayload = $this->payload($s);
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $this->postJson($this->url($s), $oldPayload)->assertUnprocessable();
        $page = $this->get($this->url($s, 'show'))->assertOk()->assertSee('Withdraw without refund');
        if (getenv('CT_WITHDRAWAL_QA') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/team-withdrawal-qa'));
            file_put_contents(storage_path('app/team-withdrawal-qa/late-review.html'), $page->getContent());
        }
        $payload = $this->payload($s);
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => 0]);
        $this->assertNotNull($s['order']->fresh()->withdrawn_at);
        $this->assertNotSame('completed', $s['order']->fresh()->refund_status);
    }

    public function test_completed_fixture_failure_leaves_payment_roster_and_ledger_unchanged(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $s['event']->id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3, 'match_status' => 2]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'team1_id' => $s['player']->id]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertSame((int) $s['player']->id, (int) $s['slot']->fresh()->player_id);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_late_refund_requires_explicit_fresh_preview_and_credits_original_payer_once(): void
    {
        $s = $this->scenario();
        $s['event']->update(['withdrawal_deadline' => now()->subDay()]);
        $payload = $this->payload($s) + ['deadline_override' => 1];
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->get($this->url($s, 'show').'?deadline_override=1')->assertOk()
            ->assertSee('R256.50')->assertSee('Reason for refund after the deadline');
        $review = app(AdminTeamWithdrawalService::class)->review($s['event'], $s['team'], $s['slot'], $s['actor'], 'wallet', 'current', 'refund', true);
        $payload['fingerprint'] = $review['fingerprint'];
        $this->actingAs($s['payer'])->postJson($this->url($s), $payload)->assertForbidden();
        $this->actingAs($s['actor']);
        $otherEvent = Event::factory()->create();
        $this->postJson(route('backend.team.withdrawal.store', [$otherEvent, $s['team'], $s['slot']]), $payload)->assertNotFound();
        $this->postJson($this->url($s), array_replace($payload, ['reason' => '']))->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 256.50]);
        $this->assertDatabaseHas('team_payment_orders', ['id' => $s['order']->id, 'pay_status' => 1, 'refund_status' => 'completed', 'refund_fee' => 28.5, 'refund_net' => 256.5]);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => 0, 'pay_status' => 0]);
        $audit = \Spatie\Activitylog\Models\Activity::where('description', 'super user authorized team withdrawal refund after deadline')->sole();
        $this->assertTrue($audit->properties['deadline_override']);
        $this->assertSame($payload['reason'], $audit->properties['reason']);
    }

    public function test_pending_private_and_unverified_payments_are_rejected_without_changes(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s);
        foreach ([['refund_status' => 'pending'], ['refund_status' => 'not_refunded', 'collection_status' => 'paid_privately'], ['collection_status' => null, 'wallet_debited' => false]] as $changes) {
            DB::table('team_payment_orders')->where('id', $s['order']->id)->update($changes);
            $this->postJson($this->url($s), $payload)->assertUnprocessable();
        }
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
    }

    public function test_duplicate_player_or_rank_places_block_refund_and_leave_all_places_untouched(): void
    {
        $s = $this->scenario();
        $payload = $this->payload($s);
        $duplicate = TeamPlayer::create(['team_id' => $s['team']->id, 'player_id' => $s['player']->id, 'rank' => 2, 'pay_status' => 1]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $duplicate->update(['player_id' => Player::factory()->create()->id, 'rank' => 1]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertSame((int) $s['player']->id, (int) $s['slot']->fresh()->player_id);
    }

    public function test_upcoming_fixture_is_cleared_but_another_event_is_preserved(): void
    {
        $s = $this->scenario();
        $otherPlayer = Player::factory()->create();
        $assignments = [];
        foreach ([$s['event'], Event::factory()->create()] as $event) {
            $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id]);
            $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3]);
            $assignments[] = \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'team1_id' => $s['player']->id, 'team2_id' => $otherPlayer->id]);
        }
        $this->post($this->url($s), $this->payload($s))->assertRedirect();
        $this->assertNull($assignments[0]->fresh()->team1_id);
        $this->assertSame((int) $otherPlayer->id, (int) $assignments[0]->fresh()->team2_id);
        $this->assertSame((int) $s['player']->id, (int) $assignments[1]->fresh()->team1_id);
        $this->assertDatabaseCount('team_fixture_players', 2);
    }

    public function test_transferred_beneficiary_refund_keeps_original_payer_and_payment_identity(): void
    {
        $s = $this->scenario();
        $originalPlayer = Player::factory()->create();
        DB::table('team_payment_orders')->where('id', $s['order']->id)->update(['player_id' => $originalPlayer->id, 'beneficiary_player_id' => $s['player']->id]);
        $s['order']->refresh();
        $this->post($this->url($s), $this->payload($s))->assertRedirect();
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 256.5]);
        $this->assertSame((int) $originalPlayer->id, (int) $s['order']->fresh()->player_id);
        $this->assertSame((int) $s['player']->id, (int) $s['order']->fresh()->beneficiary_player_id);
    }

    public function test_payfast_and_hybrid_refunds_require_exact_complete_receipts(): void
    {
        $s = $this->scenario();
        foreach ([false, true] as $hybrid) {
            $providerId = 'PF-withdrawal-'.$s['order']->id;
            $walletPart = $hybrid ? 85 : 0;
            $providerPart = 285 - $walletPart;
            DB::table('team_payment_orders')->where('id', $s['order']->id)->update(['wallet_reserved' => $walletPart, 'wallet_debited' => $hybrid,
                'payfast_paid' => true, 'payfast_pf_payment_id' => $providerId, 'payfast_amount_due' => $providerPart]);
            if ($hybrid) WalletTransaction::where('source_id', $s['order']->id)->update(['amount' => $walletPart]);
            else WalletTransaction::where('source_id', $s['order']->id)->delete();
            DB::table('transactions_pf')->insert(['custom_str5' => 'TeamOrder', 'custom_int5' => $s['order']->id,
                'custom_int2' => $s['player']->id, 'custom_int3' => $s['event']->id, 'custom_int4' => $s['payer']->id,
                'pf_payment_id' => $providerId, 'amount_gross' => $providerPart, 'payment_status' => 'FAILED']);
            $s['order']->refresh();
            $this->getJson($this->url($s, 'show'))->assertUnprocessable();
            DB::table('transactions_pf')->where('custom_int5', $s['order']->id)->update(['payment_status' => 'COMPLETE']);
            $this->post($this->url($s), $this->payload($s))->assertRedirect();
            $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 256.5]);
            if (!$hybrid) $s = $this->scenario();
        }
    }

    public function test_linked_imported_player_clears_both_roster_mirrors(): void
    {
        $s = $this->scenario();
        $s['team']->update(['noProfile' => 1]);
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'rank' => 1, 'player_profile' => $s['player']->id,
            'name' => 'Imported', 'surname' => 'Player', 'pay_status' => 1]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $s['event']->id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3]);
        $assignment = \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'team1_id' => $s['player']->id, 'team1_no_profile_id' => $imported->id]);
        $this->post($this->url($s), $this->payload($s))->assertRedirect();
        $this->assertDatabaseMissing('no_profile_team_players', ['id' => $imported->id]);
        $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => 0, 'pay_status' => 0]);
        $this->assertNull($assignment->fresh()->team1_no_profile_id);
        $this->assertNull($assignment->fresh()->team1_id);
    }

    public function test_duplicate_imported_player_at_another_rank_blocks_withdrawal(): void
    {
        $s = $this->scenario();
        $s['team']->update(['noProfile' => 1]);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'rank' => 1, 'player_profile' => $s['player']->id, 'name' => 'Imported', 'surname' => 'Player', 'pay_status' => 1]);
        $payload = $this->payload($s);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'rank' => 2, 'player_profile' => $s['player']->id, 'name' => 'Duplicate', 'surname' => 'Player', 'pay_status' => 1]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('no_profile_team_players', 2);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
    }

    public function test_historical_imported_unpaid_mirror_with_canonical_paid_receipt_can_be_reviewed_without_repairs(): void
    {
        $s = $this->scenario();
        $s['team']->update(['noProfile' => 1]);
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'rank' => 1, 'player_profile' => $s['player']->id,
            'name' => 'Imported', 'surname' => 'Player', 'pay_status' => 0]);
        WalletTransaction::query()->delete();
        $s['order']->update(['wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_paid' => true,
            'payfast_amount_due' => 285, 'payfast_pf_payment_id' => 'PF-canonical-withdrawal']);
        app(\App\Domain\Payments\Services\PaymentTransactionService::class)->record([
            'pf_payment_id' => 'PF-canonical-withdrawal', 'amount_gross' => 285, 'payment_status' => 'COMPLETE',
            'custom_str5' => 'TeamOrder', 'custom_int5' => $s['order']->id, 'custom_int2' => $s['player']->id,
            'custom_int3' => $s['event']->id, 'custom_int4' => $s['payer']->id,
        ], $s['order']);
        $this->get($this->url($s, 'show'))->assertOk()->assertSee('R256.50');
        $this->assertSame(0, (int) $imported->fresh()->pay_status);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $payload = $this->payload($s);
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->post($this->url($s), $payload)->assertRedirect();
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 256.5]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseMissing('no_profile_team_players', ['id' => $imported->id]);
    }

    public function test_imported_unpaid_mirror_with_wrong_player_or_rank_is_rejected(): void
    {
        $s = $this->scenario();
        $s['team']->update(['noProfile' => 1]);
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $s['team']->id, 'rank' => 1, 'player_profile' => $s['player']->id,
            'name' => 'Imported', 'surname' => 'Player', 'pay_status' => 0]);
        $payload = $this->payload($s);
        $imported->update(['player_profile' => Player::factory()->create()->id]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $imported->update(['player_profile' => $s['player']->id, 'rank' => 2]);
        $this->postJson($this->url($s), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
        $this->assertSame((int) $s['player']->id, (int) $s['slot']->fresh()->player_id);
    }

    public function test_sandbox_payfast_receipt_cannot_fund_a_real_wallet_refund(): void
    {
        $s = $this->scenario();
        WalletTransaction::query()->delete();
        $s['order']->update(['wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_paid' => true,
            'payfast_amount_due' => 285, 'payfast_pf_payment_id' => 'PF-test-withdrawal']);
        DB::table('transactions_pf')->insert(['custom_str5' => 'TeamOrder', 'custom_int5' => $s['order']->id,
            'custom_int2' => $s['player']->id, 'custom_int3' => $s['event']->id, 'custom_int4' => $s['payer']->id,
            'pf_payment_id' => 'PF-test-withdrawal', 'amount_gross' => 285, 'payment_status' => 'COMPLETE', 'is_test' => 1]);
        $this->getJson($this->url($s, 'show'))->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertNull($s['order']->fresh()->withdrawn_at);
    }
}
