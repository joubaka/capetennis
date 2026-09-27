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

    /**
     * @return Collection<int, array{email:string,name:string,category:string,status:string,region:string}>
     */
    public function resolveForEvent(Event $event, array $filters): Collection
    {
        $regionIds = collect($filters['event_region_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();

        if ($regionIds->isEmpty()) {
            throw ValidationException::withMessages(['event_region_ids' => 'Select at least one region.']);
        }

        $eventRegions = EventRegion::query()->with('region')
            ->where('event_id', $event->id)->whereIn('id', $regionIds)->get()->keyBy('id');
        if ($eventRegions->count() !== $regionIds->count()) {
            throw ValidationException::withMessages(['event_region_ids' => 'One or more selected regions do not belong to this event.']);
        }

        $categoryIds = collect($filters['category_event_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $validCategoryIds = Team::query()->withoutGlobalScopes()
            ->whereIn('region_id', $eventRegions->pluck('region_id'))
            ->whereIn('category_event_id', $categoryIds)
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
            ->pluck('category_event_id')->unique()->values();
        if ($categoryIds->isEmpty() || $validCategoryIds->count() !== $categoryIds->count()) {
            throw ValidationException::withMessages(['category_event_ids' => 'One or more selected age groups do not belong to the selected event regions.']);
        }

        return $regionIds->flatMap(function (int $eventRegionId) use ($event, $eventRegions, $filters, $categoryIds): Collection {
            $eventRegion = $eventRegions->get($eventRegionId);
            $regionalCategoryIds = Team::query()->withoutGlobalScopes()
                ->where('region_id', $eventRegion->region_id)
                ->whereIn('category_event_id', $categoryIds)
                ->pluck('category_event_id')->unique()->values();
            if ($regionalCategoryIds->isEmpty()) {
                return collect();
            }
            $hasSentImport = TeamSelectionImport::query()->where('event_id', $event->id)
                ->where('region_id', $eventRegion->region_id)->where('status', 'sent')->exists();
            if (! $hasSentImport) {
                return collect();
            }

            return $this->resolve($event, $eventRegion, [...$filters, 'category_event_ids' => $regionalCategoryIds->all()])
                ->map(fn (array $recipient): array => [
                    ...$recipient,
                    'region' => (string) ($eventRegion->region?->region_name ?? 'Region'),
                ]);
        })->groupBy('email')->map(function (Collection $matches): array {
            $first = $matches->first();

            return [
                ...$first,
                'category' => $matches->pluck('category')->unique()->sort()->implode(', '),
                'region' => $matches->pluck('region')->unique()->sort()->implode(', '),
            ];
        })->sortBy([['region', 'asc'], ['category', 'asc'], ['name', 'asc']])->values();
    }

    public function eventPreviewHash(Event $event, array $filters, Collection $recipients, string $sendToken): string
    {
        return hash('sha256', json_encode([
            'event_id' => (int) $event->id,
            'event_region_ids' => collect($filters['event_region_ids'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'category_event_ids' => collect($filters['category_event_ids'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'gender' => $filters['gender'],
            'audience_status' => $filters['audience_status'],
            'recipients' => $recipients->pluck('email')->map(fn ($email) => mb_strtolower(trim((string) $email)))->sort()->values()->all(),
            'send_token' => $sendToken,
        ], JSON_THROW_ON_ERROR));
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
