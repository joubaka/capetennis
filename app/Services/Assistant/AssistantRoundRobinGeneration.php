<?php

namespace App\Services\Assistant;

use App\Domain\Draws\Services\DrawGenerationService;
use App\Models\{CategoryEvent, Draw, DrawAuditLog, Event, Registration, User};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Support\Facades\{DB, Gate};
use SportStack\Assistant\ProposalFingerprint;

/** Initial generation only. Never regenerates, seeds, schedules or publishes. */
final class AssistantRoundRobinGeneration
{
    public function review(Event $event, User $actor, int $drawId, bool $lock = false): array
    {
        $query = $event->draws()->whereKey($drawId);
        $draw = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        Gate::forUser($actor)->authorize('generateFixtures', $draw);
        abort_if($draw->locked || $draw->published || $draw->oop_published || $draw->isTeamDraw(), 409, 'Initial assistant generation requires an unpublished, unlocked individual draw.');
        abort_if($draw->drawFixtures()->exists() || DB::table('team_fixtures')->where('draw_id', $draw->id)->exists()
            || DB::table('order_of_plays')->where('draw_id', $draw->id)->exists()
            || DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->exists(), 409, 'This draw already has fixtures or schedule state. Use the existing draw guide; the assistant never replaces a draw.');
        abort_if($draw->groups()->count() > 100 || DB::table('draw_group_registrations')->whereIn('draw_group_id', $draw->groups()->select('id'))->count() > 200, 422, 'Prepare at most 100 groups and 200 entrants for assistant generation.');
        if ($lock) {
            $draw->settings()->lockForUpdate()->get();
            $draw->groups()->orderBy('id')->lockForUpdate()->get();
            DB::table('draw_registrations')->where('draw_id', $draw->id)->lockForUpdate()->get();
            DB::table('draw_group_registrations')->whereIn('draw_group_id', $draw->groups()->select('id'))->lockForUpdate()->get();
        }
        $draw->load(['settings', 'groups.groupRegistrations.registration.players']);
        abort_unless($draw->settings?->workflow === 'round_robin' && $draw->category_event_id, 422, 'Choose a category and Round robin only in the draw guide first. Other formats stay in their existing workflows.');
        $category = CategoryEvent::whereKey($draw->category_event_id)->where('event_id', $event->id);
        ($lock ? $category->lockForUpdate() : $category)->firstOrFail();
        $groups = $draw->groups->sortBy('id');
        abort_if($groups->isEmpty() || $groups->count() > 100, 422, 'Prepare between 1 and 100 groups in the draw guide first.');
        $ids = $groups->flatMap(fn ($group) => $group->groupRegistrations->pluck('registration_id'))->map(fn ($id) => (int) $id)->values()->all();
        abort_if(count($ids) > 200 || count($ids) !== count(array_unique($ids)), 422, 'Prepare at most 200 unique entrants, each in exactly one group.');
        if ($lock) {
            Registration::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            DB::table('category_event_registrations')->where('category_event_id', $draw->category_event_id)->whereIn('registration_id', $ids)->orderBy('id')->lockForUpdate()->get();
            DB::table('player_registrations')->whereIn('registration_id', $ids)->orderBy('registration_id')->orderBy('player_id')->lockForUpdate()->get();
            $draw->load('groups.groupRegistrations.registration.players');
            $groups = $draw->groups->sortBy('id');
        }
        $eligible = app(FlexibleMonradService::class)->activeRegistrationIds($draw, $ids);
        abort_if(array_diff($ids, $eligible) || array_diff($ids, $draw->registrations()->pluck('registrations.id')->all()), 422, 'Every group member must still be an active paid entrant in this draw and event category.');
        $players = [];
        $review = [];
        $snapshot = [];
        $total = 0;
        foreach ($groups as $group) {
            $members = $group->groupRegistrations->sortBy('id');
            $seeds = $members->pluck('seed')->map(fn ($seed) => (int) $seed)->all();
            abort_if($members->count() < 2 || count($seeds) !== count(array_unique($seeds)) || min($seeds) < 1, 422, 'Each prepared group needs at least two entrants and unique positive seed positions.');
            foreach ($members as $member) {
                abort_unless($member->registration && $member->registration->players->count() === 1, 422, 'The first assistant generation workflow supports individual singles entrants only.');
                $players[] = (int) $member->registration->players->first()->id;
            }
            $count = $members->count();
            $matches = (int) ($count * ($count - 1) / 2);
            $total += $matches;
            $review[] = ['name' => $group->name, 'entrants' => $count, 'matches' => $matches];
            $snapshot[] = ['id' => $group->id, 'name' => $group->name, 'members' => $members->map(fn ($member) => ['id' => $member->id, 'registration_id' => $member->registration_id, 'seed' => $member->seed, 'player_ids' => $member->registration->players->pluck('id')->all()])->values()->all()];
        }
        abort_if(count($players) !== count(array_unique($players)), 422, 'A player appears in more than one group entry. Correct the draw guide first.');
        abort_if($total > 2000, 422, 'The first assistant generation workflow is limited to 2,000 fixtures.');
        return ['draw' => $draw, 'fingerprint' => ProposalFingerprint::make(['draw' => $draw->only(['id', 'event_id', 'drawName', 'drawType_id', 'category_event_id', 'locked', 'published', 'oop_published']), 'settings' => $draw->settings->toArray(), 'groups' => $snapshot]), 'preview' => ['draw_id' => $draw->id, 'name' => $draw->drawName, 'groups' => $review, 'count' => $total, 'scope' => 'Generate initial Round Robin fixtures only. Prepared groups and seed positions stay unchanged. No schedule, playoffs or publication.']];
    }

    public function apply(Event $event, User $actor, array $payload, string $proposalUuid): array
    {
        DB::table('events')->where('id', $event->id)->lockForUpdate()->first();
        $review = $this->review($event, $actor, $payload['draw_id'], true);
        abort_unless(hash_equals($payload['revision'], $review['fingerprint']), 409, 'The prepared groups, seeds or draw settings changed. Review a new generation proposal.');
        app(DrawGenerationService::class)->generateRoundRobin($review['draw']);
        $count = $review['draw']->drawFixtures()->count();
        abort_unless($count === $review['preview']['count'], 409, 'Generated fixture count differed from the reviewed plan. No changes were saved.');
        DrawAuditLog::record($review['draw']->id, 'assistant_round_robin_generated', null, ['proposal_uuid' => $proposalUuid, 'count' => $count]);
        return ['draw_id' => $review['draw']->id, 'generated' => true, 'count' => $count, 'setup_url' => route('draw.guide.show', [$event, $review['draw']])];
    }
}
