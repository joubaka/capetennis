<?php

namespace App\Domain\Finance\Services;

use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventRegion;
use App\Models\EventRegionFinancialRecipientLink;
use App\Models\EventRegionManager;
use App\Services\TeamSelection\RegionManagerAccessService;
use Illuminate\Support\Collection;

/** Read-only proposed payment view. Registration and clothing ledgers stay separate. */
class ConvenorPayoutProjection
{
    public function build(Event $event, Collection $recon, Collection $clothingReport): array
    {
        $directors = EventConvenor::with('user')->where('event_id', $event->id)
            ->where('role', '!=', 'score-keeper')->get();
        $directorUsers = $directors->groupBy('user_id');
        $eligibleRecon = $recon->filter(function ($row) use ($event, $directors) {
            $director = $row['convenor'];
            $persisted = $directors->firstWhere('id', $director->id);
            return $persisted && (int) $director->event_id === (int) $event->id
                && (int) $persisted->user_id === (int) $director->user_id;
        });
        $reconUsers = $eligibleRecon->groupBy(fn ($row) => $row['convenor']->user_id);
        $cards = $eligibleRecon->mapWithKeys(fn ($row) => [$row['convenor']->id => [
            'convenor_id' => $row['convenor']->id, 'user_id' => $row['convenor']->user_id,
            'registration_cents' => $this->cents($row['final_payout']),
            'clothing_cents' => 0, 'combined_cents' => $this->cents($row['final_payout']),
            'regions' => collect(),
        ]]);
        $cardUsers = $cards->groupBy('user_id');
        $unmatched = collect();
        $resolver = app(RegionManagerAccessService::class);
        $financialLinks = \Illuminate\Support\Facades\Schema::hasTable('event_region_financial_recipient_links')
            ? EventRegionFinancialRecipientLink::where('event_id', $event->id)->get()->groupBy('region_id') : collect();

        foreach ($clothingReport as $region) {
            $scope = $region['scope'];
            if (! $scope instanceof EventRegion || (int) $scope->event_id !== (int) $event->id) continue;
            $hasBalance = $region['balance_cents'] !== 0 || $region['pending_cents'] !== 0;
            $hasHistory = $region['entries']->isNotEmpty() || $region['refunds']->isNotEmpty();
            if (! $hasBalance && ! $hasHistory) continue;
            $manager = null;
            $recipientUserId = null;
            $recipientLink = null;
            $reason = null;
            $associations = EventRegion::where('event_id', $event->id)->where('region_id', $scope->region_id)->get(['id']);
            if (! $region['recording_available'] || $associations->count() !== 1
                || (int) $associations->first()?->id !== (int) $scope->id || ! $scope->region) {
                $reason = 'The event-region association is missing or ambiguous.';
            } else {
                $assignments = EventRegionManager::with('user')->where('event_region_id', $scope->id)->get();
                if ($assignments->count() > 1 || $assignments->contains(fn ($assignment) =>
                    (int) $assignment->event_id !== (int) $event->id
                    || (int) $assignment->region_id !== (int) $scope->region_id || ! $assignment->user)) {
                    $reason = 'The regional convenor assignment is inconsistent or ambiguous.';
                } else {
                    $manager = $resolver->manager($scope);
                    if (! $manager || ($assignments->isNotEmpty() && (int) $assignments->first()->user_id !== (int) $manager->id)) {
                        $reason = 'No unambiguous regional convenor is assigned.';
                    } else {
                        $recipientUserId = $manager->id;
                        $links = $financialLinks->get($scope->region_id, collect());
                        if ($links->isNotEmpty()) {
                            $link = $links->first();
                            $target = $directors->firstWhere('id', $link->target_convenor_id);
                            if ($links->count() !== 1 || (int) $link->event_id !== (int) $event->id
                                || (int) $link->event_region_id !== (int) $scope->id || (int) $link->region_id !== (int) $scope->region_id
                                || (int) $link->source_manager_user_id !== (int) $manager->id
                                || ! $target || ! $target->user || (int) $target->user_id !== (int) $link->target_user_id
                                || (int) $link->confirmed_by !== (int) $link->target_user_id
                                || $target->starts_at?->isFuture() || $target->expires_at?->isPast()
                                || $directorUsers->get($manager->id, collect())->isNotEmpty()) {
                                $reason = 'The confirmed financial recipient link is stale or conflicts with current director or regional assignments.';
                            } else {
                                $recipientUserId = $target->user_id;
                                $recipientLink = $link;
                            }
                        }
                    }
                    if (! $reason && ($directorUsers->get($recipientUserId, collect())->count() > 1
                        || $reconUsers->get($recipientUserId, collect())->count() > 1 || $cardUsers->get($recipientUserId, collect())->count() > 1)) {
                        $reason = 'This account has multiple event director records; its clothing balance is not applied twice.';
                    } elseif (! $reason && ($directorUsers->get($recipientUserId, collect())->count() !== 1
                        || $cardUsers->get($recipientUserId, collect())->count() !== 1)) {
                        $reason = 'The regional convenor is a different account from the event directors shown here.';
                    }
                }
            }
            if ($reason) {
                if ($hasBalance) $unmatched->push([
                    'region_name' => $scope->region?->region_name ?? 'Region not recorded',
                    'manager_name' => $manager?->name, 'reason' => $reason,
                    'balance_cents' => $region['balance_cents'], 'pending_cents' => $region['pending_cents'],
                    'available_cents' => $region['available_cents'],
                    'projected_cents' => $region['balance_cents'] < 0 ? $region['balance_cents'] : $region['available_cents'],
                ]);
                continue;
            }
            $cardId = $cardUsers->get($recipientUserId)->first()['convenor_id'];
            $card = $cards->get($cardId);
            $contribution = $region['balance_cents'] < 0 ? $region['balance_cents'] : $region['available_cents'];
            $completed = $region['refunds']->where('refund_status', 'completed');
            $refundLines = $completed->flatMap(fn ($refund) => $refund->items);
            $card['regions']->push([
                'region_id' => $scope->region_id, 'region_name' => $scope->region->region_name,
                'manager_id' => $manager->id, 'contribution_cents' => $contribution,
                'confirmed_recipient_link' => $recipientLink,
                'balance_cents' => $region['balance_cents'], 'pending_cents' => $region['pending_cents'],
                'available_cents' => $region['available_cents'], 'payout_cents' => $region['payout_cents'],
                'recovery_cents' => $region['recovery_cents'], 'offset_cents' => $region['offset_cents'],
                'shortfall_cents' => $region['shortfall_cents'],
                'completed_refund_cents' => $completed->sum(fn ($refund) => $this->cents($refund->refund_net)),
                'refunded_line_count' => $refundLines->pluck('clothing_order_item_id')->unique()->count(),
                'recorded_quantity' => $refundLines->sum('quantity'),
                'partial_amount_lines' => $refundLines->where('quantity', 0)->count(),
                'refunds' => $completed->map(fn ($refund) => [
                    'id' => $refund->id, 'amount_cents' => $this->cents($refund->refund_net),
                    'player_name' => trim($refund->order?->player?->full_name ?? '') ?: 'Player not recorded',
                    'after_payout' => (bool) $refund->after_recorded_payout, 'after_deadline' => (bool) $refund->after_recorded_deadline,
                    'refunded_at' => $refund->refunded_at,
                    'items' => $refund->items->map(fn ($line) => [
                        'name' => $line->item?->item_name ?? 'Clothing item', 'size' => $line->item?->size_name,
                        'quantity' => $line->quantity, 'amount_cents' => $this->cents($line->amount),
                    ])->values(),
                ])->values(),
            ]);
            $card['clothing_cents'] += $contribution;
            $card['combined_cents'] = $card['registration_cents'] + $card['clothing_cents'];
            $cards->put($cardId, $card);
        }

        return ['cards' => $cards, 'unmatched_regions' => $unmatched];
    }

    private function cents(mixed $amount): int
    {
        $decimal = number_format((float) $amount, 2, '.', '');
        return ($decimal[0] === '-' ? -1 : 1) * EventExpenseAmounts::cents(ltrim($decimal, '-'));
    }
}
