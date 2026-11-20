<?php

namespace App\Services;

use App\Models\{Event, EventAdmin, EventType, PracticeCampPlan, Team, TeamSelectionImport, TeamSelectionInvitation, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Private candidate preparation only. No registration, nomination publication or mail. */
final class PracticeCampPlanner
{
    public function authorizeManager(User $actor, Event $event): void
    {
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
    }

    public function authorizePlan(User $actor, PracticeCampPlan $plan): void
    {
        $this->authorizeManager($actor, $plan->event);
        foreach (collect($plan->candidates)->flatMap(fn ($row) => $row['sources'])->pluck('event_id')->unique() as $id) {
            $this->authorizeManager($actor, Event::findOrFail($id));
        }
    }

    public function create2027(User $actor): PracticeCampPlan
    {
        abort_unless($actor->hasRole('super-user'), 403);

        return DB::transaction(function () use ($actor) {
            $plan = PracticeCampPlan::firstOrCreate(['camp_key' => 'cavaliers-practice-camp-2027'], [
                'created_by' => $actor->id, 'updated_by' => $actor->id, 'revision' => (string) Str::uuid(), 'candidates' => [],
            ]);
            $plan = PracticeCampPlan::lockForUpdate()->findOrFail($plan->id);
            if ($plan->event_id) {
                return $plan;
            }
            $type = EventType::where('type', EventType::CAMP)->orderBy('id')->first()
                ?? EventType::where('type', EventType::INDIVIDUAL)->where('name', 'not like', '%trial%')->orderBy('id')->first();
            if (! $type) {
                throw ValidationException::withMessages(['camp' => 'An ordinary camp or individual event type must be available.']);
            }
            $event = Event::create([
                'name' => 'Cavaliers Practice Camp 2027', 'start_date' => '2027-01-07', 'end_date' => '2027-01-09',
                'eventType' => $type->id, 'information' => '<p>Practice camp planning draft. Dates are provisional. Venue, fees, capacity and entry arrangements are still to be confirmed.</p>',
                // Legacy events require a numeric fee. The plan's nullable fee is authoritative while preparing.
                'entryFee' => 0, 'deadline' => 0, 'logo' => '', 'status' => 'draft', 'published' => false, 'signUp' => false,
            ]);
            EventAdmin::firstOrCreate(['event_id' => $event->id, 'user_id' => $actor->id]);
            $plan->update(['event_id' => $event->id]);

            return $plan;
        });
    }

    public function teams(User $actor, Event $source, int $regionId): Collection
    {
        $this->authorizeManager($actor, $source);
        abort_unless($source->regions()->where('team_regions.id', $regionId)->exists(), 404);

        return app(EventTeamScope::class)->query($source)->where('region_id', $regionId)
            ->with(['category.category', 'team_players.player', 'team_players_no_profile.profile'])
            ->orderBy('name')->limit(101)->get();
    }

    public function preview(User $actor, Event $source, int $regionId, array $teamIds): array
    {
        $teams = $this->teams($actor, $source, $regionId);
        abort_if($teams->count() > 100, 422, 'Choose a smaller source region.');
        abort_unless($teams->pluck('id')->intersect($teamIds)->count() === count($teamIds), 404);
        $teams = $teams->whereIn('id', $teamIds);
        $region = $source->regions()->where('team_regions.id', $regionId)->firstOrFail();
        $imports = TeamSelectionImport::where('event_id', $source->id)->where('region_id', $regionId)
            ->whereIn('status', ['draft', 'sent'])->orderByDesc('id')->limit(1)->pluck('id');
        $invitations = TeamSelectionInvitation::where('event_id', $source->id)->where('region_id', $regionId)
            ->whereIn('import_id', $imports)->whereIn('team_id', $teams->modelKeys())->with('player')->orderBy('id')->limit(2001)->get();
        abort_if($invitations->count() > 2000, 422, 'Choose fewer source teams.');
        $rows = collect();
        foreach ($teams as $team) {
            $sourceData = ['event_id' => (int) $source->id, 'event' => $source->name, 'region_id' => $regionId,
                'region' => $region->region_name, 'team_id' => (int) $team->id, 'team' => $team->name,
                'division' => $team->category?->category?->name];
            $members = $team->noProfile ? $team->team_players_no_profile : $team->team_players;
            foreach ($members as $member) {
                $player = $team->noProfile ? $member->profile : $member->player;
                if (! $team->noProfile && ! $player) {
                    continue;
                }
                // Imported slots retain their identity. A link/name alone never proves a cross-roster match.
                $key = $team->noProfile ? 'imported:'.$team->id.':'.$member->id : 'player:'.$player->id;
                $rows->push(['key' => $key, 'name' => $team->noProfile ? trim($member->name.' '.$member->surname) : $player->full_name,
                    'player_id' => $team->noProfile ? null : (int) $player->id,
                    'identity' => $team->noProfile ? 'Imported roster identity — needs matching before invitations' : 'Existing player',
                    'sources' => [$sourceData + ['kind' => 'roster', 'status' => 'Current roster; participation not inferred', 'roster_rank' => $member->rank]],
                ]);
            }
            foreach ($invitations->where('team_id', $team->id)->whereIn('status', [TeamSelectionInvitation::DECLINED, TeamSelectionInvitation::WITHDRAWN]) as $invitation) {
                if (! $invitation->player) {
                    continue;
                }
                $manual = data_get($invitation->snapshot_json, 'selection_source') === 'manual_system_profile';
                $rows->push(['key' => 'player:'.$invitation->player_id, 'name' => $invitation->player->full_name,
                    'player_id' => (int) $invitation->player_id, 'identity' => 'Existing player',
                    'sources' => [$sourceData + ['kind' => 'selection_history', 'status' => ucfirst($invitation->status).' — eligibility needs review',
                        'invitation_id' => (int) $invitation->id, 'original_rank' => $manual ? null : data_get($invitation->snapshot_json, 'ranking_position'),
                        'manual_selection' => $manual]],
                ]);
            }
        }
        abort_if($rows->count() > 2000, 422, 'Choose fewer teams for this review.');
        $rows = $this->merge([], $rows->all());

        return ['rows' => $rows, 'fingerprint' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'notice' => $imports->isEmpty() ? 'No managed selection history is available here. Original selections and declines may need separate records.'
                : 'Historical declines are review candidates, not an automatic ranking cutoff. Manual sequence numbers are not merit rankings.'];
    }

    public function add(User $actor, PracticeCampPlan $plan, Event $source, array $data): void
    {
        DB::transaction(function () use ($actor, $plan, $source, $data) {
            $locked = PracticeCampPlan::lockForUpdate()->findOrFail($plan->id);
            $this->authorizePlan($actor, $locked);
            abort_unless(hash_equals($locked->revision, $data['revision']), 409, 'The camp draft changed. Reload before saving.');
            $preview = $this->preview($actor, $source, (int) $data['region_id'], $data['team_ids']);
            abort_unless(hash_equals($preview['fingerprint'], $data['fingerprint']), 409, 'Source rosters changed. Review a fresh preview.');
            $rows = collect($preview['rows'])->keyBy('key');
            abort_unless($rows->keys()->intersect($data['selected'])->count() === count($data['selected']), 422, 'Choose players from this preview only.');
            $candidates = $this->merge($locked->candidates, $rows->only($data['selected'])->values()->all());
            abort_if(count($candidates) > 2000, 422, 'The draft candidate limit is 2,000.');
            $locked->update(['candidates' => $candidates, 'revision' => (string) Str::uuid(), 'updated_by' => $actor->id]);
        });
    }

    public function remove(User $actor, PracticeCampPlan $plan, string $revision, string $key): void
    {
        DB::transaction(function () use ($actor, $plan, $revision, $key) {
            $locked = PracticeCampPlan::lockForUpdate()->findOrFail($plan->id);
            $this->authorizePlan($actor, $locked);
            abort_unless(hash_equals($locked->revision, $revision), 409, 'The camp draft changed. Reload before saving.');
            $locked->update(['candidates' => collect($locked->candidates)->reject(fn ($row) => $row['key'] === $key)->values()->all(),
                'revision' => (string) Str::uuid(), 'updated_by' => $actor->id]);
        });
    }

    private function merge(array $existing, array $additional): array
    {
        $merged = collect($existing)->keyBy('key');
        foreach ($additional as $row) {
            $old = $merged->get($row['key']);
            if ($old) {
                $row['sources'] = collect([...$old['sources'], ...$row['sources']])
                    ->unique(fn ($source) => $source['event_id'].':'.$source['team_id'].':'.$source['kind'])->values()->all();
            }
            $merged->put($row['key'], $row);
        }

        return $merged->sortKeys()->values()->all();
    }
}
