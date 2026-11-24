<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\EventEntrySummary;
use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Event};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEntrySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_unpaid_merge_retirement_is_not_a_participant_withdrawal(): void
    {
        $division = CategoryEvent::factory()->create();
        $retired = CategoryEventRegistration::factory()->withdrawn()->create(['category_event_id' => $division->id, 'payment_status_id' => 0]);
        $real = CategoryEventRegistration::factory()->withdrawn()->create(['category_event_id' => $division->id, 'payment_status_id' => 0, 'withdrawal_reason' => 'Duplicate profile']);
        activity('player-profile-merge')->performedOn($retired)->withProperties([
            'duplicate_registration_id' => $retired->registration_id,
            'canonical_registration_id' => $real->registration_id,
            'category_event_id' => $division->id,
        ])->log('Retired unpaid duplicate registration during player merge');
        // An unrelated audit subject or incorrect division cannot hide a real withdrawal.
        activity('player-profile-merge')->performedOn($real)->withProperties([
            'duplicate_registration_id' => $real->registration_id,
            'category_event_id' => $division->id + 100,
        ])->log('Retired unpaid duplicate registration during player merge');
        $totals = app(EventEntrySummary::class)->build($division->event)['totals'];
        $this->assertSame(1, $totals['withdrawn']);
        $this->assertSame(1, $totals['recorded']);
        $this->assertSame([$real->id], app(EventEntrySummary::class)->withdrawals($division->event)->pluck('id')->all());
    }

    public function test_team_reentry_beneficiary_and_foreign_event_orders_remain_separate(): void
    {
        $type = \App\Models\EventType::unguarded(fn () => \App\Models\EventType::create(['name' => 'Team summary', 'type' => \App\Models\EventType::TEAM]));
        $event = Event::factory()->create(['eventType' => $type->id]);
        $foreign = Event::factory()->create(['eventType' => $type->id]);
        $division = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = \App\Models\Team::factory()->create(['category_event_id' => $division->id]);
        $players = \App\Models\Player::factory()->count(2)->create();
        foreach ($players as $i => $player) {
            \App\Models\TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $i + 1, 'pay_status' => 1]);
        }
        $base = ['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $players[0]->id, 'user_id' => \App\Models\User::factory()->create()->id, 'total_amount' => 100, 'pay_status' => true, 'refund_status' => 'completed', 'withdrawn_at' => now()];
        $withdrawn = \App\Models\TeamPaymentOrder::create($base);
        // Effective beneficiary is player two: player one's active place must survive.
        $withdrawn->forceFill(['beneficiary_player_id' => $players[1]->id])->save();
        $totals = app(EventEntrySummary::class)->build($event)['totals'];
        $this->assertSame(1, $totals['confirmed']);
        $this->assertSame(1, $totals['withdrawn']);
        // A foreign-event order pointing at this team must not restore player two's place.
        \App\Models\TeamPaymentOrder::create([...$base, 'event_id' => $foreign->id, 'player_id' => $players[1]->id, 'withdrawn_at' => null]);
        $this->assertSame($totals, app(EventEntrySummary::class)->build($event)['totals']);
        \App\Models\TeamPaymentOrder::create([...$base, 'player_id' => $players[1]->id, 'withdrawn_at' => null, 'refund_status' => 'not_refunded']);
        $reentry = app(EventEntrySummary::class)->build($event)['totals'];
        $this->assertSame(2, $reentry['confirmed']);
        $this->assertSame(1, $reentry['withdrawn']);
        $this->assertSame(3, $reentry['recorded']);
        $this->assertSame(1, $reentry['completed']);
        $detail = app(EventEntrySummary::class)->withdrawals($event);
        $this->assertSame(1, $detail->total());
        $this->assertSame(trim($players[1]->name.' '.$players[1]->surname), $detail->first()->players);
    }

    public function test_team_rosters_do_not_double_count_imported_mirrors_or_cancelled_unpaid_orders(): void
    {
        $type = \App\Models\EventType::unguarded(fn () => \App\Models\EventType::create(['name' => 'Team summary', 'type' => \App\Models\EventType::TEAM]));
        $event = Event::factory()->create(['eventType' => $type->id]);
        $division = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = \App\Models\Team::factory()->create(['category_event_id' => $division->id]);
        $imported = \App\Models\Team::factory()->create(['category_event_id' => $division->id, 'noProfile' => true]);
        $players = \App\Models\Player::factory()->count(3)->create();
        foreach ($players as $i => $player) {
            \App\Models\TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $i + 1, 'pay_status' => $i !== 1]);
        }
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $imported->id, 'name' => 'Example', 'surname' => 'Player', 'rank' => 1, 'player_profile' => $players[0]->id, 'pay_status' => 1]);
        \App\Models\TeamPlayer::create(['team_id' => $imported->id, 'player_id' => $players[0]->id, 'rank' => 1, 'pay_status' => 1]);
        foreach ([[$players[1]->id, false], [$players[2]->id, true]] as [$playerId, $paid]) {
            \App\Models\TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $playerId, 'user_id' => \App\Models\User::factory()->create()->id, 'total_amount' => 100, 'pay_status' => $paid, 'withdrawn_at' => now(), 'refund_status' => 'not_refunded']);
        }
        $totals = app(EventEntrySummary::class)->build($event)['totals'];
        $this->assertSame(2, $totals['confirmed']);
        $this->assertSame(1, $totals['unpaid']);
        $this->assertSame(1, $totals['withdrawn']);
        $this->assertSame(4, $totals['recorded']);
    }

    public function test_entry_counts_include_withdrawal_history_and_exclude_checkout_attempts_and_other_events(): void
    {
        $event = Event::factory()->create();
        $division = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Girls U12'])->id]);
        $other = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $create = fn (array $attributes = []) => CategoryEventRegistration::factory()->create(['category_event_id' => $division->id, ...$attributes]);
        $create(['payment_status_id' => 1]);
        $create(['payment_status_id' => 0]);
        $create(['status' => 'pending_checkout']);
        $create(['deleted_at' => now()]);
        foreach (['completed', 'pending', 'waived', '', "'not_refunded'"] as $status) {
            $create(['status' => 'withdrawn', 'withdrawn_at' => now(), 'refund_status' => $status, 'payment_status_id' => 1, 'deleted_at' => now()]);
        }
        CategoryEventRegistration::factory()->create(['category_event_id' => $other->id, 'payment_status_id' => 1]);
        CategoryEventRegistration::factory()->withdrawn()->create();
        $before = CategoryEventRegistration::withTrashed()->count();
        $summary = app(EventEntrySummary::class)->build($event);
        $this->assertSame(['recorded' => 8, 'confirmed' => 2, 'unpaid' => 1, 'withdrawn' => 5, 'completed' => 1, 'pending' => 1, 'waived' => 1, 'no_refund' => 2], $summary['totals']);
        $girls = collect($summary['divisions'])->firstWhere('name', 'Girls U12');
        $this->assertSame(7, $girls['recorded']);
        $this->assertSame(1, $girls['confirmed']);
        $this->assertSame($summary, app(EventEntrySummary::class)->build($event));
        $this->assertSame($before, CategoryEventRegistration::withTrashed()->count());
    }
}
