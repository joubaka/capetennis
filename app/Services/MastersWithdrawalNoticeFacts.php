<?php

namespace App\Services;

use App\Models\CategoryEventRegistration;
use App\Models\MastersInvitation;

final class MastersWithdrawalNoticeFacts
{
    public static function html(MastersInvitation $invitation): string
    {
        $entry = $invitation->registration_id && $invitation->categoryEvent?->event_id === $invitation->batch?->event_id
            ? CategoryEventRegistration::withTrashed()->where('registration_id', $invitation->registration_id)
                ->where('category_event_id', $invitation->category_event_id)
                ->whereHas('players', fn ($query) => $query->where('players.id', $invitation->player_id))
                ->first()
            : null;

        return WithdrawalNoticeFacts::html($invitation->player?->full_name ?? 'Unknown player',
            $entry?->withdrawn_at ?? $invitation->withdrawn_at, $entry?->withdrawn_by);
    }
}
