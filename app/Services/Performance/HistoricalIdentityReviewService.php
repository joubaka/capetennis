<?php

namespace App\Services\Performance;

use App\Models\{Event, HistoricalIdentityProposal, NoProfileTeamPlayer, Player, Team, TeamFixturePlayer};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Proposal-only review: never writes identities, results, publication, ratings or refresh state. */
class HistoricalIdentityReviewService
{
    private function authorize(): void
    {
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
    }

    public function listings(Event $event): array
    {
        $this->authorize();
        $rows = TeamFixturePlayer::query()->with(['fixture.draw', 'fixture.teamResults', 'noProfile1', 'noProfile2'])
            ->whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
            ->whereHas('fixture.teamResults')
            ->where(fn ($q) => $q->whereNotNull('team1_no_profile_id')->orWhereNotNull('team2_no_profile_id'))
            ->orderBy('id')->paginate(20, ['*'], 'matches_page');
        $items = [];
        foreach ($rows as $row) {
            foreach ([1, 2] as $side) {
                $imported = $row->{'noProfile'.$side};
                if (!$imported) { continue; }
                $team = Team::find($imported->team_id);
                if ($team && app(ImportedMatchIdentityResolver::class)->resolve($row, $side, $team, (int) $event->id)) { continue; }
                $items[] = ['row' => $row, 'side' => $side, 'imported' => $imported, 'team' => $team,
                    'fingerprint' => $this->sourceFingerprint($row, $team),
                    'blocker' => 'Missing or inconsistent historical identity evidence. A current profile link is not an original attestation.'];
            }
        }
        $proposals = HistoricalIdentityProposal::where('event_id', $event->id)->orderByDesc('id')->paginate(30, ['*'], 'proposals_page');
        return compact('rows', 'items', 'proposals');
    }

    public function propose(Event $event, array $input): HistoricalIdentityProposal
    {
        $this->authorize();
        $this->require(strlen(trim($input['evidence'] ?? '')) >= 30 && strlen(trim($input['reference'] ?? '')) >= 5
            && strlen(trim($input['reason'] ?? '')) >= 15, 'Provide substantive evidence, a source reference and a reason.');
        // Read/validate first in the same transaction as the proposal insert. This never changes sources.
        return DB::transaction(function () use ($event, $input) {
            $identity = $this->validateIdentity($event, (int) $input['fixture_player_id'], (int) $input['side'], (int) $input['source_team_id'], (int) $input['suggested_player_id']);
            $fingerprint = $this->sourceFingerprint($identity['row'], $identity['team']);
            $this->require(hash_equals($fingerprint, $input['source_fingerprint']), 'Source changed since this form was loaded. Reload and review the evidence.');
            return HistoricalIdentityProposal::create([
                'event_id' => $event->id, 'fixture_player_id' => $identity['row']->id, 'side' => (int) $input['side'],
                'source_team_id' => $identity['team']->id, 'suggested_player_id' => $identity['player']->id,
                'created_by' => auth()->id(), 'source_fingerprint' => $fingerprint,
                'target_fingerprint' => $this->targetFingerprint($identity['player']), 'cohort' => $identity['cohort'],
                'evidence' => trim($input['evidence']), 'reference' => trim($input['reference']), 'reason' => trim($input['reason']),
                'created_at' => now(),
            ]);
        });
    }

    private function require(bool $condition, string $message): void
    {
        if (!$condition) { throw ValidationException::withMessages(['proposal' => $message]); }
    }

    private function validateIdentity(Event $event, int $rowId, int $side, int $teamId, int $playerId): array
    {
        $this->require(in_array($side, [1, 2], true), 'Choose the exact fixture side.');
        $row = TeamFixturePlayer::with(['fixture.draw.categoryEvent.category', 'fixture.teamTie', 'fixture.teamResults'])->findOrFail($rowId);
        $fixture = $row->fixture;
        $this->require((int) $fixture?->draw?->event_id === (int) $event->id, 'Fixture belongs to another event.');
        $this->require($fixture->fixturePlayers()->count() === 1 && !$fixture->isDoubles(), 'Only unambiguous singles fixtures are supported.');
        $this->require($fixture->teamResults->isNotEmpty(), 'Historical scored evidence is required.');
        $importedId = $row->{'team'.$side.'_no_profile_id'};
        $this->require((bool) $importedId && !$row->{'team'.$side.'_id'}, 'The selected side must contain exactly one imported identity.');
        $imported = NoProfileTeamPlayer::findOrFail($importedId);
        $team = Team::with(['category.category', 'regions'])->findOrFail($teamId);
        $this->require((int) $imported->team_id === $teamId, 'Imported participant belongs to another source team.');
        $this->require((int) $team->category?->event_id === (int) $event->id, 'Source team must have an explicit category in this event.');
        $this->require((bool) $team->region_id && $event->regions()->where('team_regions.id', $team->region_id)->exists(), 'Source region is not explicitly part of this event.');
        if ($fixture->teamTie) {
            $this->require((int) $fixture->teamTie->draw_id === (int) $fixture->draw_id
                && (int) $fixture->teamTie->{($side === 1 ? 'home_team_id' : 'away_team_id')} === $teamId, 'Source team does not match the fixture tie and side.');
        } else {
            $this->require((int) $fixture->{'region'.$side} === (int) $team->region_id, 'Source region does not match this fixture side.');
        }
        $rank = (int) $imported->rank;
        $this->require($rank > 0 && NoProfileTeamPlayer::where('team_id', $teamId)->where('rank', $rank)->count() === 1, 'Imported roster rank is missing or ambiguous.');
        $snapshot = $row->participant_snapshot[$side] ?? [];
        $this->require(!$snapshot || ((!isset($snapshot['source_team_id']) || (int) $snapshot['source_team_id'] === $teamId)
            && (!isset($snapshot['rank']) || (int) $snapshot['rank'] === $rank)), 'Existing historical evidence conflicts with the proposed source.');
        $label = $team->category->category->name;
        $cohort = app(PlayerSharedAbilityService::class)->cohort(app(PlayerPerformancePilotService::class)->division($label)['cohort']);
        $this->require((bool) preg_match('/^u(\d{1,2}) (boys|girls)$/', $cohort, $parts), 'Source category must have an exact age and gender.');
        $drawLabel = $fixture->draw->categoryEvent?->category?->name ?? $fixture->draw->drawName;
        $drawCohort = app(PlayerSharedAbilityService::class)->cohort(app(PlayerPerformancePilotService::class)->division($drawLabel)['cohort']);
        $this->require(str_starts_with($drawCohort, $cohort) || preg_match('/\bsingles\b/i', $drawLabel) && !preg_match('/\bu\d|boys|girls/i', $drawLabel), 'Draw and source category disagree.');
        $player = Player::findOrFail($playerId);
        $this->require(!$imported->player_profile || (int) $imported->player_profile === $playerId, 'The current imported profile link conflicts with this suggestion; resolve that conflict separately before preview.');
        $mirrors = $team->team_players()->where('rank', $rank)->get();
        $this->require($mirrors->count() <= 1 && !$mirrors->contains(fn ($member) => $member->player_id && (int) $member->player_id !== $playerId), 'Current roster rank has a conflicting or duplicate profile.');
        $this->require(!NoProfileTeamPlayer::where('team_id', $teamId)->where('id', '!=', $imported->id)->where('player_profile', $playerId)->exists()
            && !$team->team_players()->where('rank', '!=', $rank)->where('player_id', $playerId)->exists(), 'Suggested player occupies another roster slot.');
        $this->require((bool) $player->dateOfBirth && preg_match('/^\d{4}-\d{2}-\d{2}$/', substr((string) $player->dateOfBirth, 0, 10)), 'Suggested player needs a valid birth date.');
        try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', substr((string) $player->dateOfBirth, 0, 10)); }
        catch (\Throwable) { throw ValidationException::withMessages(['proposal' => 'Suggested player birth date is invalid.']); }
        $year = CarbonImmutable::parse($event->start_date)->year;
        $age = $year - $birth->year;
        $this->require($age >= 5 && $age <= (int) $parts[1], 'Suggested profile is over-age or has an implausible age for this historical category.');
        $gender = strtolower(trim((string) $player->gender));
        $this->require(in_array($gender, $parts[2] === 'girls' ? ['female', 'girl', 'girls', 'f'] : ['male', 'boy', 'boys', 'm'], true), 'Suggested profile gender conflicts with the category.');
        $opponent = $row->{'team'.($side === 1 ? 2 : 1).'_id'};
        $this->require((int) $opponent !== $playerId, 'A player cannot be both sides of this match.');
        if ($event->frontend_type_view === 'masters') { $cohort = 'masters · '.$cohort; }
        return compact('row', 'team', 'player', 'cohort');
    }

    private function sourceFingerprint(TeamFixturePlayer $row, ?Team $team): string
    {
        $fixture = $row->fixture()->with(['draw.categoryEvent.category', 'teamTie', 'teamResults'])->first();
        $imports = NoProfileTeamPlayer::whereIn('id', array_filter([$row->team1_no_profile_id, $row->team2_no_profile_id]))->orderBy('id')->get()->map->getRawOriginal()->all();
        return hash('sha256', json_encode([$row->getRawOriginal(), $fixture?->getRawOriginal(), $fixture?->draw?->getRawOriginal(),
            $fixture?->draw?->categoryEvent?->toArray(), $fixture?->teamTie?->getRawOriginal(), $fixture?->teamResults->map->getRawOriginal()->all(),
            $team?->getRawOriginal(), $team?->category?->toArray(), $team?->regions?->getRawOriginal(), $imports,
            $team?->team_players()->orderBy('id')->get()->map->getRawOriginal()->all(),
            Event::find($fixture?->draw?->event_id)?->getRawOriginal(),
            DB::table('event_regions')->where('event_id', $fixture?->draw?->event_id)->orderBy('id')->get()->all()], JSON_THROW_ON_ERROR));
    }

    private function targetFingerprint(Player $player): string
    {
        return hash('sha256', json_encode($player->only(['id', 'name', 'surname', 'dateOfBirth', 'gender']), JSON_THROW_ON_ERROR));
    }

    public function preview(Event $event, array $ids): array
    {
        $this->authorize();
        return app(PlayerAbilityConsistentRead::class)->run(function () use ($event, $ids) {
            $proposals = HistoricalIdentityProposal::where('event_id', $event->id)->whereIn('id', $ids)->get();
            $this->require($proposals->count() === count(array_unique($ids)), 'Choose proposals belonging to this event.');
            $identities = []; $cohorts = [];
            foreach ($proposals as $proposal) {
                $this->require(strlen(trim($proposal->evidence)) >= 30 && strlen(trim($proposal->reference)) >= 5 && strlen(trim($proposal->reason)) >= 15, 'Each proposal needs substantive evidence, a source reference and a reason.');
                $identity = $this->validateIdentity($event, $proposal->fixture_player_id, $proposal->side, $proposal->source_team_id, $proposal->suggested_player_id);
                $this->require(hash_equals($proposal->source_fingerprint, $this->sourceFingerprint($identity['row'], $identity['team']))
                    && hash_equals($proposal->target_fingerprint, $this->targetFingerprint($identity['player'])) && $proposal->cohort === $identity['cohort'], 'Proposal evidence is stale. Create a new revision after reviewing the changed source/profile.');
                $this->require(!isset($identities[$event->id][$proposal->fixture_player_id][$proposal->side]), 'Select only one proposal revision for each match side.');
                $identities[$event->id][$proposal->fixture_player_id][$proposal->side] = $identity;
                $cohorts[] = $identity['cohort'];
            }
            $asOf = CarbonImmutable::today('Africa/Johannesburg');
            $service = app(PlayerSharedAbilityService::class);
            $fingerprint = $service->fingerprint();
            $baseline = $service->identityReviewSnapshot($asOf, cohorts: array_unique($cohorts));
            $candidate = $service->identityReviewSnapshot($asOf, new HistoricalIdentityPreviewContext($identities), array_unique($cohorts));
            $this->require(!$baseline['reason'] && !$candidate['reason'] && hash_equals($fingerprint, $service->fingerprint()), 'Source data changed or the calculation exceeded its safe limits. No preview is available.');
            return ['as_of' => $asOf->toDateString(), 'fingerprint' => $fingerprint, 'proposals' => $proposals,
                'cohorts' => $this->comparison($baseline, $candidate),
                'added_matches' => array_values(array_diff($candidate['source_matches'], $baseline['source_matches']))];
        });
    }

    private function comparison(array $before, array $after): array
    {
        $result = [];
        foreach (array_unique([...array_keys($before['cohorts']), ...array_keys($after['cohorts'])]) as $label) {
            $old = $before['cohorts'][$label] ?? ['ratings' => [], 'components' => []];
            $new = $after['cohorts'][$label] ?? ['ratings' => [], 'components' => []];
            $positions = function (array $data): array {
                $out = [];
                foreach ($data['components'] as $component => $group) {
                    $members = $group['members'];
                    usort($members, fn ($a, $b) => ($data['ratings'][$b]['strength'] <=> $data['ratings'][$a]['strength']) ?: ($a <=> $b));
                    foreach ($members as $index => $id) { $out[$id] = $index + 1; }
                }
                return $out;
            };
            $oldPositions = $positions($old); $newPositions = $positions($new); $rows = [];
            foreach (array_unique([...array_keys($old['ratings']), ...array_keys($new['ratings'])]) as $id) {
                $a = $old['ratings'][$id] ?? null; $b = $new['ratings'][$id] ?? null;
                $oldMembers = $a ? $old['components'][$a['component']]['members'] : [];
                $newMembers = $b ? $new['components'][$b['component']]['members'] : [];
                sort($oldMembers); sort($newMembers);
                $rows[] = ['id' => $id, 'name' => $after['names'][$id] ?? $before['names'][$id] ?? 'Player #'.$id,
                    'before' => $a, 'after' => $b, 'old_position' => $oldPositions[$id] ?? null, 'new_position' => $newPositions[$id] ?? null,
                    'topology_changed' => $oldMembers !== $newMembers, 'new_node' => !$a,
                    'cutoff' => (($oldPositions[$id] ?? 0) >= 8 && ($oldPositions[$id] ?? 99) <= 12) || (($newPositions[$id] ?? 0) >= 8 && ($newPositions[$id] ?? 99) <= 12),
                    'top10_changed' => (($oldPositions[$id] ?? 999) <= 10) !== (($newPositions[$id] ?? 999) <= 10)];
            }
            $result[$label] = ['rows' => $rows, 'before_components' => count($old['components']), 'after_components' => count($new['components'])];
        }
        return $result;
    }
}
