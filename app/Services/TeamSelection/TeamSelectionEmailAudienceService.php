<?php

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\EventRegion;
use App\Models\Team;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TeamSelectionEmailAudienceService
{
    public function __construct(private TeamSelectionContactService $contacts) {}

    /**
     * @return Collection<int, array{email:string,name:string,category:string,status:string}>
     */
    public function resolve(Event $event, EventRegion $eventRegion, array $filters): Collection
    {
        $categoryIds = collect($filters['category_event_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            throw ValidationException::withMessages([
                'category_event_ids' => 'Select at least one age group.',
            ]);
        }

        $validCategoryIds = Team::query()
            ->withoutGlobalScopes()
            ->where('region_id', $eventRegion->region_id)
            ->whereIn('category_event_id', $categoryIds)
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
            ->pluck('category_event_id')
            ->unique()
            ->values();
        if ($validCategoryIds->count() !== $categoryIds->count()) {
            throw ValidationException::withMessages([
                'category_event_ids' => 'One or more selected age groups do not belong to this event.',
            ]);
        }

        $activeImportId = TeamSelectionImport::query()
            ->where('event_id', $event->id)
            ->where('region_id', $eventRegion->region_id)
            ->where('status', 'sent')
            ->latest('id')
            ->value('id');

        if (! $activeImportId) {
            throw ValidationException::withMessages([
                'audience_status' => 'This region has no sent team-selection import to email.',
            ]);
        }

        $invitations = TeamSelectionInvitation::query()
            ->with(['player.user', 'player.users', 'team.category.category'])
            ->where('event_id', $event->id)
            ->where('region_id', $eventRegion->region_id)
            ->where('import_id', $activeImportId)
            ->whereHas('team', fn ($query) => $query->whereIn('category_event_id', $categoryIds))
            ->get();

        $gender = $filters['gender'] ?? 'any';
        if ($gender !== 'any') {
            $genderId = $gender === 'boys' ? 1 : 2;
            $invitations = $invitations->filter(fn (TeamSelectionInvitation $invitation) => (int) $invitation->player?->gender === $genderId);
        }

        $audience = $filters['audience_status'] ?? 'active';
        $invitations = $invitations->filter(fn (TeamSelectionInvitation $invitation) => $this->matchesStatus($invitation, $audience));

        return $invitations
            ->map(function (TeamSelectionInvitation $invitation): array {
                return [
                    'email' => (string) $this->contacts->primaryEmail($invitation->player),
                    'name' => (string) ($invitation->player?->full_name ?? 'Player'),
                    'category' => (string) ($invitation->team?->category?->category?->name ?? $invitation->team?->name ?? 'Age group'),
                    'status' => $invitation->status,
                ];
            })
            ->filter(fn (array $recipient) => filled($recipient['email']))
            ->map(fn (array $recipient) => [...$recipient, 'email' => mb_strtolower(trim($recipient['email']))])
            ->unique('email')
            ->sortBy([['category', 'asc'], ['name', 'asc']])
            ->values();
    }

    private function matchesStatus(TeamSelectionInvitation $invitation, string $audience): bool
    {
        return match ($audience) {
            'active' => in_array($invitation->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true),
            'entered' => $invitation->status === TeamSelectionInvitation::PAID_CONFIRMED,
            'not_entered' => in_array($invitation->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ], true),
            'invited' => $invitation->invited_at !== null && in_array($invitation->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true),
            'not_invited' => $invitation->status === TeamSelectionInvitation::RESERVE
                || ($invitation->status === TeamSelectionInvitation::INVITED && $invitation->invited_at === null),
            'accepted' => in_array($invitation->status, [
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true),
            'not_accepted' => $invitation->status === TeamSelectionInvitation::INVITED
                && $invitation->invited_at !== null
                && $invitation->accepted_at === null,
            'declined' => $invitation->status === TeamSelectionInvitation::DECLINED,
            'withdrawn' => $invitation->status === TeamSelectionInvitation::WITHDRAWN,
            default => false,
        };
    }
}
