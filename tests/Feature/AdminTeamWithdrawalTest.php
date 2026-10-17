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
}
