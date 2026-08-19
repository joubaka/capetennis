<?php

namespace App\Services;

use App\Models\DisciplinarySanction;
use App\Models\Event;
use App\Models\Player;
use RuntimeException;
use App\Models\RegistrationOrder;

class PlayerEligibilityService
{
    public function activeRestrictions(Player|int $player, Event|int|null $event = null)
    {
        $playerId = $player instanceof Player ? $player->id : $player;
        $eventModel = $event instanceof Event ? $event : ($event ? Event::find($event) : null);

        return DisciplinarySanction::query()
            ->effective()
            ->where('player_id', $playerId)
            ->whereIn('type', ['suspension', 'disqualification', 'interim_restriction'])
            ->where(function ($query) use ($eventModel) {
                $query->where('scope', 'global');
                if ($eventModel) {
                    $query->orWhere(fn ($q) => $q->where('scope', 'event')->where('scope_id', $eventModel->id));
                    if ($eventModel->series_id) {
                        $query->orWhere(fn ($q) => $q->where('scope', 'series')->where('scope_id', $eventModel->series_id));
                    }
                }
            })
            ->orderBy('ends_at')
            ->get();
    }

    public function assertEligible(Player|int $player, Event|int $event): void
    {
        $restriction = $this->activeRestrictions($player, $event)->first();
        if (! $restriction) {
            return;
        }

        $until = $restriction->ends_at?->format('d M Y') ?? 'further notice';
        throw new RuntimeException("Player is not eligible for this event due to an active {$restriction->type} until {$until}. Case {$restriction->disciplinaryCase?->case_number}.");
    }

    public function assertOrderEligible(RegistrationOrder $order): void
    {
        $order->loadMissing('items.category_event.event');
        foreach ($order->items as $item) {
            if ($item->player_id && $item->category_event?->event) {
                $this->assertEligible((int) $item->player_id, $item->category_event->event);
            }
        }
    }
}
