<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\{TeamPaymentService, TeamReplacementPaymentCorrectionService};
use App\Models\{CategoryEvent, Event, NoProfileTeamPlayer, Player, Team, TeamPaymentOrder, TeamPlayer, User, WalletTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event as Events, Http, Mail};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamReplacementPaymentCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Correct mistaken refund-funded replacement to carry original payment coverage';

    private function scenario(bool $imported = false): array
    {
        Http::preventStrayRequests();
        Mail::fake();
        Events::fake([\App\Events\RefundCompleted::class, \App\Events\PaymentCompleted::class]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create();
        $event = Event::factory()->create(['entryFee' => 490, 'withdrawal_deadline' => now()->addDays(3), 'signUp' => true, 'deadline' => 0, 'start_date' => now()->addWeek()]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => $imported, 'num_team_members' => 10]);
        $original = Player::factory()->create();
        $target = Player::factory()->create();
        $slot = TeamPlayer::create(['team_id' => $team->id, 'player_id' => $original->id, 'rank' => 10, 'pay_status' => 1]);
        $raw = $imported ? NoProfileTeamPlayer::create(['team_id' => $team->id, 'player_profile' => $original->id, 'rank' => 10,
            'name' => $original->name, 'surname' => $original->surname, 'pay_status' => 0]) : null;
        $source = TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $original->id, 'user_id' => $payer->id,
            'total_amount' => 490, 'wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_paid' => true, 'payfast_amount_due' => 490,
            'payfast_pf_payment_id' => 'PF-original-correction-'.$original->id, 'pay_status' => true]);
        DB::table('transactions_pf')->insert(['custom_str5' => 'TeamOrder', 'custom_int5' => $source->id, 'custom_int2' => $original->id,
            'custom_int3' => $event->id, 'custom_int4' => $payer->id, 'pf_payment_id' => $source->payfast_pf_payment_id,
            'amount_gross' => 490, 'payment_status' => 'COMPLETE', 'is_test' => 0]);
        $this->actingAs($actor)->postJson(route('backend.team.changePlayer'), ['event_id' => $event->id, 'team_id' => $team->id,
            'slot_id' => $raw?->id ?? $slot->id, 'expected_player_id' => $original->id, 'expected_rank' => 10,
            'player_id' => $target->id, 'refund_action' => 'replacement', 'reason' => 'Original roster replacement'])->assertOk();
        $replacement = TeamPaymentOrder::where('team_id', $team->id)->where('player_id', $target->id)->sole();
        $this->travel(6)->minutes();
        $this->assertTrue(app(TeamPaymentService::class)->expireUnpaidCheckout($replacement));
        $this->travelBack();
        $wallet = $payer->wallet()->firstOrFail();
        $credit = WalletTransaction::where('source_type', 'admin_team_withdrawal_refund')->where('source_id', $source->id)->sole();
        $ids = ['event_id' => $event->id, 'team_id' => $team->id, 'source_order_id' => $source->id, 'replacement_order_id' => $replacement->id,
            'source_player_id' => $original->id, 'target_player_id' => $target->id];
        return compact('actor', 'payer', 'event', 'team', 'original', 'target', 'slot', 'source', 'replacement', 'wallet', 'credit', 'ids');
    }

    private function reject(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected correction validation to reject this state.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function upcomingFixture(array $s, bool $mixed = false, bool $importedIdentity = false): array
    {
        $anchor = $s['team'];
        $selection = ['category_ids' => [$s['team']->category_event_id]];
        if ($mixed) {
            $s['team']->category->category->update(['name' => 'U/12 Girls']);
            $boysCategory = CategoryEvent::factory()->create(['event_id' => $s['event']->id,
                'category_id' => \App\Models\Category::factory()->create(['name' => 'U/12 Boys'])->id]);
            $anchor = Team::factory()->create(['category_event_id' => $boysCategory->id, 'region_id' => $s['team']->region_id]);
            TeamPlayer::create(['team_id' => $anchor->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1, 'pay_status' => 0]);
            $selection = ['category_ids' => [$s['team']->category_event_id, $boysCategory->id],
                'mixed_sides' => [$anchor->id => ['boys' => $anchor->id, 'girls' => $s['team']->id, 'region_id' => (int) $s['team']->region_id]]];
        }
        $opponent = Team::factory()->create(['category_event_id' => $s['team']->category_event_id]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $s['event']->id]);
        $draw->forceFill(['team_draw_selection' => $selection])->save();
        $tie = \App\Models\TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $anchor->id, 'away_team_id' => $opponent->id, 'status' => 'draft']);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1,
            'match_nr' => 1, 'round_nr' => 1, 'numSets' => 3, 'match_status' => 0, 'scheduled_at' => now()->addWeek(), 'court_label' => 'Court 1', 'duration_min' => 120]);
        $importId = $importedIdentity ? NoProfileTeamPlayer::where('team_id', $s['team']->id)->where('player_profile', $s['target']->id)->sole()->id : null;
        $assignment = \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1,
            'team1_id' => $importedIdentity ? null : $s['target']->id, 'team1_no_profile_id' => $importId]);
        $assignment->forceFill(['participant_snapshot' => [1 => ['event_id' => $s['event']->id, 'source_team_id' => $s['team']->id,
            'category_event_id' => $s['team']->category_event_id, 'region_id' => (int) $s['team']->region_id,
            'profile_id' => $importedIdentity ? null : $s['target']->id, 'imported_id' => $importId,
            'linked_profile_id' => $importedIdentity ? $s['target']->id : null, 'rank' => 10]]])->save();
        return compact('draw', 'tie', 'fixture', 'assignment');
    }

    public function test_preview_is_read_only_and_correction_compensates_refund_and_restores_original_coverage_exactly_once(): void
    {
        foreach ([false, true] as $imported) {
            $s = $this->scenario($imported);
            $service = app(TeamReplacementPaymentCorrectionService::class);
            $sourceBefore = $s['source']->fresh()->getAttributes();
            $closedBefore = $s['replacement']->fresh()->getAttributes();
            $creditBefore = $s['credit']->getAttributes();
            $receiptBefore = DB::table('transactions_pf')->where('custom_int5', $s['source']->id)->first();
            $auditCount = Activity::count();
            $refundBefore = TeamPaymentOrder::where('event_id', $s['event']->id)->where('refund_status', 'completed')->sum('refund_net');
            $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
            $this->assertEquals(441, $preview['refund_to_reverse']);
            $this->assertEquals(0, $preview['wallet_after']);
            $this->assertSame($sourceBefore, $s['source']->fresh()->getAttributes());
            $this->assertSame($auditCount, Activity::count());
            $this->assertSame(1, WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $result = $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']);
            $this->assertTrue($result['applied']);
            $this->assertTrue($service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint'])['already_applied']);
            $source = $s['source']->fresh();
            $this->assertSame($s['target']->id, $source->effective_player_id);
            foreach (['player_id', 'user_id', 'pay_status', 'total_amount', 'payfast_paid', 'payfast_amount_due', 'payfast_pf_payment_id', 'wallet_reserved', 'wallet_debited'] as $field) {
                $this->assertSame($sourceBefore[$field], $source->getAttributes()[$field]);
            }
            $this->assertNull($source->withdrawn_at);
            $this->assertNull($source->refunded_at);
            $this->assertSame('not_refunded', $source->refund_status);
            $this->assertEquals(0, $source->refund_gross + $source->refund_fee + $source->refund_net);
            $this->assertNull($source->refund_method);
            $this->assertSame($creditBefore, $s['credit']->fresh()->getAttributes());
            $this->assertSame($closedBefore, $s['replacement']->fresh()->getAttributes());
            $this->assertEquals($receiptBefore, DB::table('transactions_pf')->where('custom_int5', $s['source']->id)->first());
            $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $s['wallet']->id, 'type' => 'debit', 'amount' => 441,
                'source_type' => 'team_replacement_refund_reversal', 'source_id' => $source->id]);
            $this->assertEquals(0, $s['wallet']->fresh()->balance);
            $this->assertSame(2, WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $this->assertSame(1, DB::table('team_payment_transfers')->where('order_id', $source->id)->count());
            $this->assertDatabaseHas('team_players', ['id' => $s['slot']->id, 'player_id' => $s['target']->id, 'pay_status' => 1]);
            if ($imported) $this->assertDatabaseHas('no_profile_team_players', ['team_id' => $s['team']->id, 'rank' => 10, 'player_profile' => $s['target']->id, 'pay_status' => 1]);
            $audit = Activity::where('subject_id', $source->id)->where('description', TeamReplacementPaymentCorrectionService::AUDIT)->sole();
            $this->assertSame($s['actor']->id, (int) $audit->causer_id);
            $this->assertSame('completed', $audit->properties['before']['refund_status']);
            $this->assertSame('not_refunded', $audit->properties['after']['refund_status']);
            $this->assertSame(self::REASON, $audit->properties['reason']);
            $refundAfter = TeamPaymentOrder::where('event_id', $s['event']->id)->where('refund_status', 'completed')->sum('refund_net');
            $this->assertEquals(441, $refundBefore - $refundAfter);
            Mail::assertNothingSent();
            Mail::assertNothingQueued();
            Http::assertNothingSent();
        }
    }

    public function test_state_fingerprint_binds_wallet_actor_reason_and_replay(): void
    {
        $s = $this->scenario();
        $service = app(TeamReplacementPaymentCorrectionService::class);
        $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
        $this->reject(fn () => $service->apply($s['actor'], $s['ids'], 'Different reason', $preview['fingerprint']));
        $other = User::factory()->create()->assignRole('super-user');
        $this->reject(fn () => $service->apply($other, $s['ids'], self::REASON, $preview['fingerprint']));
        WalletTransaction::create(['wallet_id' => $s['wallet']->id, 'type' => 'credit', 'amount' => 1, 'source_type' => 'test', 'source_id' => 1]);
        $this->reject(fn () => $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']));
        $fresh = $service->preview($s['actor'], $s['ids'], self::REASON);
        $service->apply($s['actor'], $s['ids'], self::REASON, $fresh['fingerprint']);
        $this->reject(fn () => $service->apply($other, $s['ids'], self::REASON, $fresh['fingerprint']));
        $this->reject(fn () => $service->apply($s['actor'], $s['ids'], 'Different reason', $fresh['fingerprint']));
        $this->assertEquals(1, $s['wallet']->fresh()->balance);
    }

    public function test_spent_or_reserved_refund_and_corrupt_audit_or_price_are_rejected_without_changes(): void
    {
        foreach (['spent', 'reserved', 'audit', 'price', 'provider', 'source_player', 'cross_event'] as $case) {
            $s = $this->scenario();
            if ($case === 'spent') WalletTransaction::create(['wallet_id' => $s['wallet']->id, 'type' => 'debit', 'amount' => 1, 'source_type' => 'test', 'source_id' => 1]);
            if ($case === 'reserved') TeamPaymentOrder::create(['event_id' => $s['event']->id, 'team_id' => $s['team']->id,
                'user_id' => $s['payer']->id, 'player_id' => Player::factory()->create()->id, 'total_amount' => 10, 'wallet_reserved' => 10, 'wallet_debited' => false, 'pay_status' => false]);
            if ($case === 'audit') Activity::where('subject_id', $s['source']->id)->where('description', 'applied withdrawal refund to replacement checkout')->delete();
            if ($case === 'price') $s['event']->update(['entryFee' => 491]);
            if ($case === 'provider') DB::table('team_payment_orders')->where('id', $s['replacement']->id)->update(['payfast_handed_off_at' => now()]);
            if ($case === 'source_player') $s['ids']['source_player_id'] = Player::factory()->create()->id;
            if ($case === 'cross_event') $s['ids']['event_id'] = Event::factory()->create()->id;
            $sourceBefore = $s['source']->fresh()->getAttributes();
            $count = WalletTransaction::count();
            try {
                app(TeamReplacementPaymentCorrectionService::class)->preview($s['actor'], $s['ids'], self::REASON);
                $this->fail('Expected unsafe correction state to be rejected.');
            } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $this->assertTrue(true);
            }
            $this->assertSame($sourceBefore, $s['source']->fresh()->getAttributes());
            $this->assertSame($count, WalletTransaction::count());
            $this->assertDatabaseMissing('wallet_transactions', ['source_type' => 'team_replacement_refund_reversal', 'source_id' => $s['source']->id]);
        }
    }

    public function test_non_super_user_cannot_preview_or_apply_and_command_defaults_to_preview(): void
    {
        $s = $this->scenario();
        $service = app(TeamReplacementPaymentCorrectionService::class);
        foreach (['preview', 'apply'] as $method) {
            try {
                $method === 'preview' ? $service->preview($s['payer'], $s['ids'], self::REASON) : $service->apply($s['payer'], $s['ids'], self::REASON, str_repeat('a', 64));
                $this->fail('Expected forbidden correction.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $arguments = ['event' => $s['event']->id, 'team' => $s['team']->id, 'source' => $s['source']->id, 'replacement' => $s['replacement']->id,
            'source-player' => $s['original']->id, 'target-player' => $s['target']->id, '--actor' => $s['actor']->id, '--reason' => self::REASON];
        $this->artisan('finance:correct-team-replacement', $arguments)->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->artisan('finance:correct-team-replacement', $arguments + ['--apply' => true])->expectsOutputToContain('fingerprint')->assertFailed();
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame('completed', $s['source']->fresh()->refund_status);
    }

    public function test_failure_after_refund_compensation_rolls_back_wallet_projection_and_transfer_together(): void
    {
        $s = $this->scenario();
        $service = app(TeamReplacementPaymentCorrectionService::class);
        $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
        $sourceBefore = $s['source']->fresh()->getAttributes();
        $this->partialMock(TeamPaymentService::class, function ($mock): void {
            $mock->shouldReceive('restoreCorrectedReplacementCoverage')->once()->andThrow(new \RuntimeException('Simulated coverage failure'));
        });
        try {
            $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']);
            $this->fail('Expected the simulated correction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated coverage failure', $exception->getMessage());
        }
        $this->assertSame($sourceBefore, $s['source']->fresh()->getAttributes());
        $this->assertSame(1, WalletTransaction::count());
        $this->assertEquals(441, $s['wallet']->fresh()->balance);
        $this->assertSame(0, DB::table('team_payment_transfers')->count());
        $this->assertSame(0, Activity::whereIn('description', ['reversed erroneous team replacement wallet refund', TeamReplacementPaymentCorrectionService::AUDIT])->count());
    }

    public function test_corrected_source_requires_separate_reconciliation_for_any_new_refund(): void
    {
        $s = $this->scenario(true);
        $service = app(TeamReplacementPaymentCorrectionService::class);
        $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
        $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']);
        $source = $s['source']->fresh();
        $actions = [
            fn () => app(\App\Services\AdminTeamWithdrawalService::class)->review($s['event'], $s['team'], $s['slot']->fresh(), $s['actor']),
            fn () => app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTeamRefund($source, ['refund_method' => 'bank', 'refund_status' => 'pending'], $s['payer']),
            fn () => app(\App\Domain\Refunds\Services\RefundExecutionService::class)->executeWalletRefund($source, $s['wallet'], 441, 'admin_team_withdrawal_refund', $source->id),
        ];
        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('Expected a separate reconciliation requirement.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('requires separate administrator reconciliation', $exception->getMessage());
            }
        }
        $this->assertSame(2, WalletTransaction::count());
        $this->assertFalse((bool) $source->fresh()->withdrawn_at);
        $this->assertSame('not_refunded', $source->fresh()->refund_status);
    }

    public function test_imported_only_other_team_and_replayed_result_drift_are_rejected(): void
    {
        $s = $this->scenario();
        $other = Team::factory()->create(['category_event_id' => $s['team']->category_event_id, 'noProfile' => true]);
        $duplicate = NoProfileTeamPlayer::create(['team_id' => $other->id, 'player_profile' => $s['target']->id,
            'rank' => 1, 'name' => $s['target']->name, 'surname' => $s['target']->surname, 'pay_status' => 0]);
        $service = app(TeamReplacementPaymentCorrectionService::class);
        $this->reject(fn () => $service->preview($s['actor'], $s['ids'], self::REASON));
        $duplicate->delete();
        $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
        $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']);
        DB::table('team_players')->where('id', $s['slot']->id)->update(['pay_status' => 0]);
        $this->reject(fn () => $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']));
        $this->assertSame(2, WalletTransaction::count());
    }

    public function test_verified_upcoming_profile_imported_and_mixed_fixtures_remain_byte_exact_after_payment_correction(): void
    {
        foreach (['profile', 'imported', 'mixed'] as $case) {
            $s = $this->scenario($case === 'imported');
            $f = $this->upcomingFixture($s, $case === 'mixed', $case === 'imported');
            $f['draw']->update(['published' => true, 'oop_published' => true, 'locked' => true]);
            $before = array_map(fn ($model) => $model->fresh()->getAttributes(), $f);
            $service = app(TeamReplacementPaymentCorrectionService::class);
            $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
            $this->assertSame([$f['fixture']->id], $preview['unchanged_upcoming_fixture_ids']);
            $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']);
            foreach ($f as $key => $model) $this->assertSame($before[$key], $model->fresh()->getAttributes());
            $this->assertSame(1, (int) $s['slot']->fresh()->pay_status);
            $this->assertEquals(0, $s['wallet']->fresh()->balance);
        }
    }

    public function test_started_scored_source_ambiguous_and_unverified_fixture_participation_is_rejected(): void
    {
        foreach (['started', 'scored', 'completed_tie', 'missing_tie', 'other_team', 'snapshot', 'source', 'source_import', 'mixed_mapping', 'mixed_anchor_mapping'] as $case) {
            $s = $this->scenario($case === 'source_import');
            $f = $this->upcomingFixture($s, $case === 'mixed_mapping');
            if ($case === 'started') $f['fixture']->update(['match_status' => 1]);
            if ($case === 'scored') $f['fixture']->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
            if ($case === 'completed_tie') $f['tie']->update(['status' => 'completed']);
            if ($case === 'missing_tie') $f['fixture']->update(['team_tie_id' => null]);
            if ($case === 'other_team') $f['tie']->update(['home_team_id' => $f['tie']->away_team_id]);
            if ($case === 'snapshot') $f['assignment']->forceFill(['participant_snapshot' => []])->save();
            if ($case === 'source') $f['assignment']->update(['team2_id' => $s['original']->id]);
            if ($case === 'source_import') {
                $withdrawal = Activity::where('subject_id', $s['source']->id)->where('description', 'super user confirmed team withdrawal')->sole();
                $f['assignment']->update(['team2_no_profile_id' => data_get($withdrawal->properties, 'original_imported_slots.0.id')]);
            }
            if ($case === 'mixed_mapping') {
                $selection = $f['draw']->team_draw_selection;
                $selection['mixed_sides'][$f['tie']->home_team_id]['region_id'] = 999;
                $f['draw']->forceFill(['team_draw_selection' => $selection])->save();
            }
            if ($case === 'mixed_anchor_mapping') {
                $selection = $f['draw']->team_draw_selection;
                $selection['mixed_sides'] = [$s['team']->id => ['boys' => $s['team']->id, 'girls' => $f['tie']->away_team_id, 'region_id' => 999]];
                $f['draw']->forceFill(['team_draw_selection' => $selection])->save();
            }
            $this->reject(fn () => app(TeamReplacementPaymentCorrectionService::class)->preview($s['actor'], $s['ids'], self::REASON));
            $this->assertSame(1, WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $this->assertSame('completed', $s['source']->fresh()->refund_status);
        }
    }

    public function test_fixture_creation_score_and_schedule_changes_invalidate_payment_correction_preview(): void
    {
        foreach (['new_fixture', 'score', 'schedule', 'started'] as $case) {
            $s = $this->scenario();
            $f = $this->upcomingFixture($s);
            $service = app(TeamReplacementPaymentCorrectionService::class);
            $preview = $service->preview($s['actor'], $s['ids'], self::REASON);
            if ($case === 'new_fixture') $this->upcomingFixture($s);
            if ($case === 'score') $f['fixture']->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
            if ($case === 'schedule') $f['fixture']->update(['court_label' => 'Court 2']);
            if ($case === 'started') $f['fixture']->update(['match_status' => 1]);
            $this->reject(fn () => $service->apply($s['actor'], $s['ids'], self::REASON, $preview['fingerprint']));
            $this->assertSame(1, WalletTransaction::where('wallet_id', $s['wallet']->id)->count());
            $this->assertSame('completed', $s['source']->fresh()->refund_status);
        }
    }
}
