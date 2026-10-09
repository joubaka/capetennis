<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamTie;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TeamTieValidationService
 *
 * Validates roster constraints, player eligibility, gender/category
 * rules, and duplicate-assignment rules before persisting.
 *
 * All public methods throw \InvalidArgumentException on failure, or
 * return normally on success.
 */
class TeamTieValidationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Roster Validation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that a team's active roster satisfies the format's size constraints.
     *
     * @param  Team              $team
     * @param  TeamEventFormat   $format
     * @throws \InvalidArgumentException
     */
    public function assertRosterSize(Team $team, TeamEventFormat $format): void
    {
        $count = $this->rosterCount($team);

        if ($count < $format->min_roster_size) {
            throw new \InvalidArgumentException(
                "Team \"{$team->name}\" has only {$count} player(s), but the format requires " .
                "at least {$format->min_roster_size}."
            );
        }

        if ($count > $format->max_roster_size) {
            throw new \InvalidArgumentException(
                "Team \"{$team->name}\" has {$count} player(s), which exceeds the format maximum " .
                "of {$format->max_roster_size}."
            );
        }
    }

    /**
     * Assert that a team's roster does not exceed the hard cap of 12 active players.
     *
     * @param  Team  $team
     * @throws \InvalidArgumentException
     */
    public function assertHardRosterCap(Team $team): void
    {
        $count = $this->rosterCount($team);

        if ($count > 12) {
            throw new \InvalidArgumentException(
                "Team \"{$team->name}\" has {$count} active players, which exceeds the maximum of 12."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Player Eligibility
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that all given player IDs belong to the specified team in the draw context.
     *
     * @param  array<int>  $playerIds
     * @param  int         $teamId
     * @param  int         $drawId   Used for context in error messages.
     * @throws \InvalidArgumentException
     */
    public function assertPlayersFromTeam(array $playerIds, int $teamId, int $drawId): void
    {
        if (empty($playerIds)) {
            return;
        }

        $team = app(TeamDrawSideResolver::class)->side(Draw::findOrFail($drawId), Team::with('team_players')->findOrFail($teamId));

        $validIds = $team->team_players->pluck('player_id')->toArray();

        $invalid = array_diff($playerIds, $validIds);

        if (!empty($invalid)) {
            throw new \InvalidArgumentException(
                "Player(s) [" . implode(', ', $invalid) . "] do not belong to team \"{$team->name}\" " .
                "(draw #{$drawId})."
            );
        }
    }

    /**
     * Assert no player appears more than once in a tie when player reuse is disabled.
     *
     * @param  array<int>        $playerIds    All player IDs assigned so far in this tie.
     * @param  array<int>        $newPlayerIds Additional player IDs being assigned.
     * @param  TeamEventFormat   $format
     * @throws \InvalidArgumentException
     */
    public function assertNoDuplicateAssignment(
        array          $playerIds,
        array          $newPlayerIds,
        TeamEventFormat $format
    ): void {
        if ($format->allow_player_reuse) {
            return;
        }

        $duplicates = array_intersect($playerIds, $newPlayerIds);

        if (!empty($duplicates)) {
            throw new \InvalidArgumentException(
                "Player(s) [" . implode(', ', $duplicates) . "] are already assigned in this tie " .
                "and the format does not allow player reuse."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Gender / Category Rules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert that the given player IDs satisfy the rubber's gender rule.
     *
     * Gender rule values: 'male' | 'female' | 'mixed'
     *
     * For 'mixed': expects exactly one male and one female among all provided players.
     * For 'male' / 'female': all players must match.
     *
     * Falls back gracefully when player gender is not set (warns, does not reject).
     *
     * @param  array<int>  $playerIds
     * @param  string      $genderRule
     * @param  string      $rubberCode  For error context.
     * @throws \InvalidArgumentException
     */
    public function assertGenderRule(array $playerIds, string $genderRule, string $rubberCode): void
    {
        if (empty($playerIds) || $genderRule === '') {
            return;
        }

        $players = Player::whereIn('id', $playerIds)->get();

        if ($players->isEmpty()) {
            return;
        }

        // Normalise to string: 1 → 'male', 2 → 'female', string passthrough
        $genders = $players->pluck('gender')->filter()->map(function ($g) {
            if ($g === 1 || $g === '1') return 'male';
            if ($g === 2 || $g === '2') return 'female';
            return strtolower((string) $g);
        });

        if ($genders->isEmpty()) {
            // Gender is not recorded for these players. Rather than blocking assignment,
            // we log a warning and allow the operation. This is a deliberate fallback —
            // operators can still manually validate gender compliance before publishing.
            // If strict gender enforcement is required, ensure player gender is populated.
            \Log::warning("[TeamTieValidationService] Players have no gender set; skipping gender rule check.", [
                'player_ids'  => $playerIds,
                'rubber_code' => $rubberCode,
            ]);
            return;
        }

        match ($genderRule) {
            'male'   => $this->assertAllGender($genders, 'male', $rubberCode),
            'female' => $this->assertAllGender($genders, 'female', $rubberCode),
            'mixed'  => $this->assertMixed($genders, $rubberCode),
            default  => null, // Unknown rule — allow and log
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tie Completeness
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assert required rubbers and their complete, distinct player slots exist.
     *
     * @param  TeamTie  $tie
     * @throws \InvalidArgumentException
     */
    public function assertTieComplete(TeamTie $tie): void
    {
        $rubbers = $tie->rubbers()->with('fixturePlayers')->get();
        if ($rubbers->isEmpty()) {
            throw new \InvalidArgumentException("Tie #{$tie->id} has no rubbers.");
        }
        $this->assertRequiredRubbersPresent($tie);
        $snapshot = $tie->format_snapshot ?? $tie->draw?->team_format_snapshot;
        if ($tie->draw?->team_draw_selection) {
            $resolver = app(TeamDrawSideResolver::class);
            $tie->setRelation('homeTeam', $resolver->side($tie->draw, $tie->homeTeam));
            $tie->setRelation('awayTeam', $resolver->side($tie->draw, $tie->awayTeam));
        }
        if (is_array($snapshot) && isset($snapshot['min_roster_size'], $snapshot['max_roster_size'])) {
            $snapshotFormat = new TeamEventFormat($snapshot);
            foreach ([$tie->homeTeam, $tie->awayTeam] as $team) {
                if ($team) {
                    $this->assertRosterSize($team, $snapshotFormat);
                }
            }
        }
        $seen = [1 => [], 2 => []];
        $incomplete = [];

        foreach ($rubbers as $rubber) {
            $expected = (int) ($rubber->player_count_per_team ?: ($rubber->isSingles() ? 1 : 2));
            $sides = [];
            foreach ([1, 2] as $side) {
                $sides[$side] = $rubber->fixturePlayers->map(function ($slot) use ($side) {
                    $profile = $slot->{"team{$side}_id"};
                    $imported = $slot->{"team{$side}_no_profile_id"};
                    // A slot must identify exactly one player source.
                    return $profile && !$imported ? "player:{$profile}"
                        : ($imported && !$profile ? "imported:{$imported}" : null);
                })->filter()->unique()->count();
            }
            if ($rubber->fixturePlayers->count() !== $expected || $sides[1] !== $expected || $sides[2] !== $expected) {
                $incomplete[] = "Rubber #{$rubber->rubber_sequence} ({$rubber->rubber_name})";
            }
            if (is_array($snapshot)) {
                foreach ([1 => $tie->homeTeam, 2 => $tie->awayTeam] as $side => $team) {
                    if (!$team || ($team->category && (int) $team->category->event_id !== (int) $tie->draw->event_id)) {
                        throw new \InvalidArgumentException('Tie teams must belong to the draw event.');
                    }
                    $profileIds = $rubber->fixturePlayers->pluck("team{$side}_id")->filter()->all();
                    $importedIds = $rubber->fixturePlayers->pluck("team{$side}_no_profile_id")->filter()->all();
                    if ($tie->draw->team_draw_selection['mixed_sides'] ?? []) {
                        app(TeamDrawSideResolver::class)->assertMixedSources($tie->draw, $team->id, $profileIds, $importedIds);
                    }
                    $historicalProfiles = []; $historicalImported = [];
                    $map = $tie->draw->team_draw_selection['mixed_sides'][$team->id] ?? null;
                    $sourceIds = $map ? [$map['boys'], $map['girls']] : [$team->id];
                    foreach ($rubber->fixturePlayers as $slot) {
                        $participant = $slot->participant_snapshot[$side] ?? null;
                        if (!$participant || !in_array($participant['source_team_id'], $sourceIds, true)) continue;
                        $source = \App\Models\Team::find($participant['source_team_id']);
                        if ($source && app(TeamParticipantHistoryService::class)->matches($slot, $side, $source, (int) $tie->draw->event_id)) {
                            if ($participant['profile_id']) $historicalProfiles[] = $participant['profile_id'];
                            if ($participant['imported_id']) $historicalImported[] = $participant['imported_id'];
                        }
                    }
                    if (array_diff($profileIds, array_merge($team->team_players->pluck('player_id')->all(), $historicalProfiles))
                        || array_diff($importedIds, array_merge($team->team_players_no_profile->pluck('id')->all(), $historicalImported))) {
                        throw new \InvalidArgumentException('Rubber players must belong to their assigned team.');
                    }
                    $keys = array_merge(array_map(fn ($id) => "player:{$id}", $profileIds), array_map(fn ($id) => "imported:{$id}", $importedIds));
                    if (!($snapshot['allow_player_reuse'] ?? false) && array_intersect($seen[$side], $keys)) {
                        throw new \InvalidArgumentException('This format does not allow a player to be reused within a tie.');
                    }
                    $seen[$side] = array_merge($seen[$side], $keys);
                    $definition = collect($snapshot['rubbers'] ?? [])->firstWhere('sequence', $rubber->rubber_sequence);
                    $genderRule = $definition['gender_rule'] ?? $rubber->gender_rule;
                    if ($genderRule) {
                        // Imported players have no recorded gender. Validate only known facts.
                        $genders = Player::whereIn('id', $profileIds)->pluck('gender')->filter()->map(fn ($gender) => in_array($gender, [1, '1'], true) ? 'male' : (in_array($gender, [2, '2'], true) ? 'female' : strtolower((string) $gender)));
                        if (in_array($genderRule, ['male', 'female'], true)) {
                            $this->assertAllGender($genders, $genderRule, $rubber->rubber_code);
                        } elseif ($genderRule === 'mixed' && $genders->count() === $expected) {
                            $this->assertMixed($genders, $rubber->rubber_code);
                        }
                    }
                }
            }
        }

        if (!empty($incomplete)) {
            throw new \InvalidArgumentException(
                "Tie #{$tie->id} is incomplete. Missing player assignments for: " .
                implode(', ', $incomplete) . '.'
            );
        }
    }

    public function assertRequiredRubbersPresent(TeamTie $tie): void
    {
        if (!$this->requiredRubbersPresent($tie)) {
            throw new \InvalidArgumentException("Tie #{$tie->id} is missing required rubbers.");
        }
    }

    public function requiredRubbersPresent(TeamTie $tie): bool
    {
        $snapshot = $tie->format_snapshot ?? $tie->draw?->team_format_snapshot;
        $required = is_array($snapshot)
            ? collect($snapshot['rubbers'] ?? [])->filter(fn ($rubber) => $rubber['is_required'] ?? true)->pluck('sequence')->all()
            : ($tie->draw?->teamEventFormat?->rubbers->where('is_required', true)->pluck('sequence')->all() ?? []);
        return !array_diff($required, $tie->rubbers()->pluck('rubber_sequence')->all());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function rosterCount(Team $team): int
    {
        // A claimed import retains its row for fixture and payment history. Only
        // pair it with its exact profile roster position, leaving both sources intact.
        $profiles = $team->team_players->groupBy(fn ($member) =>
            $member->team_id.':'.$member->rank.':'.$member->player_id
        )->map->count()->all();
        $count = $team->team_players->count();
        foreach ($team->team_players_no_profile as $member) {
            $key = $member->team_id.':'.$member->rank.':'.$member->player_profile;
            if ((int) $member->player_profile > 0 && (int) $member->rank > 0 && ($profiles[$key] ?? 0) > 0) {
                $profiles[$key]--;
            } else {
                $count++;
            }
        }

        return $count;
    }

    private function assertAllGender(Collection $genders, string $expected, string $rubberCode): void
    {
        $invalid = $genders->reject(fn($g) => $g === $expected);

        if ($invalid->isNotEmpty()) {
            throw new \InvalidArgumentException(
                "Rubber \"{$rubberCode}\" requires all players to be {$expected}. " .
                "Found: " . $invalid->implode(', ') . '.'
            );
        }
    }

    private function assertMixed(Collection $genders, string $rubberCode): void
    {
        $hasMale   = $genders->contains('male');
        $hasFemale = $genders->contains('female');

        if (!$hasMale || !$hasFemale) {
            throw new \InvalidArgumentException(
                "Rubber \"{$rubberCode}\" is mixed doubles and requires at least one male and one female player. " .
                "Found genders: " . $genders->implode(', ') . '.'
            );
        }
    }
}
