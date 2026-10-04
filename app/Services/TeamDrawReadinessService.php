<?php

namespace App\Services;

use App\Models\TeamEventFormat;
use Illuminate\Support\Collection;

/** Produces the same lineup choices as generation without creating any records. */
class TeamDrawReadinessService
{
    public function __construct(
        private TeamDrawGenerationService $generator,
        private TeamPlayerAutoAssignService $assigner,
        private TeamTieValidationService $validator,
    ) {}

    public function preview(Collection $teams, ?TeamEventFormat $format): array
    {
        $warnings = [];
        if ($teams instanceof \Illuminate\Database\Eloquent\Collection) {
            $teams->loadMissing(['team_players.player', 'team_players_no_profile']);
        }

        if (!$format) {
            $warnings[] = 'No format selected or configured as the event default. Player readiness cannot be assessed.';
        } elseif ($format->rubbers->isEmpty()) {
            $warnings[] = 'The selected format has no rubber definitions.';
        }
        if ($teams->count() < 2) {
            $warnings[] = 'At least two teams are required for round-robin ties.';
        }
        if ($format) {
            foreach ($teams as $team) {
                try {
                    $this->validator->assertRosterSize($team, $format);
                } catch (\InvalidArgumentException $exception) {
                    $warnings[] = $exception->getMessage();
                }
            }
        }
        $rounds = [];
        $tieCount = $rubberCount = $missingSlots = $byeCount = 0;
        $schedule = $teams->count() >= 2 ? $this->generator->buildRoundRobinSchedule($teams) : [];
        foreach ($schedule as $roundNumber => $matches) {
            $ties = [];
            $playing = [];
            foreach ($matches as [$home, $away]) {
                $playing[] = $home->id;
                $playing[] = $away->id;
                $rubbers = [];
                $seen = [1 => [], 2 => []];
                foreach ($format?->rubbers ?? [] as $template) {
                    $slots = $this->assigner->resolveSlots($template, $home, $away);
                    $missing = 0;
                    foreach ($slots as &$slot) {
                        foreach ([1 => $home, 2 => $away] as $side => $team) {
                            $profile = $slot['team'.$side.'_id'] ?? null;
                            $imported = $slot['team'.$side.'_no_profile_id'] ?? null;
                            $member = $profile ? $team->team_players->firstWhere('player_id', $profile)?->player
                                : ($imported ? $team->team_players_no_profile->firstWhere('id', $imported) : null);
                            $slot['team'.$side.'_name'] = $member ? ($profile ? $member->full_name : trim($member->name.' '.$member->surname)) : null;
                            if (!$profile && !$imported) {
                                $missing++;
                            } elseif (($profile && $imported) || !$member) {
                                $warnings[] = "{$team->name}: {$template->name} has an invalid roster assignment.";
                            }
                        }
                    }
                    unset($slot);
                    foreach ([1 => $home, 2 => $away] as $side => $team) {
                        $keys = [];
                        $genders = [];
                        foreach ($slots as $slot) {
                            $profile = $slot['team'.$side.'_id'] ?? null;
                            $imported = $slot['team'.$side.'_no_profile_id'] ?? null;
                            if (!$profile && !$imported) { continue; }
                            $keys[] = $profile ? 'player:'.$profile : 'imported:'.$imported;
                            $gender = $profile ? $team->team_players->firstWhere('player_id', $profile)?->player?->gender : null;
                            $genders[] = in_array($gender, [1, '1'], true) ? 'male'
                                : (in_array($gender, [2, '2'], true) ? 'female' : strtolower((string) $gender));
                        }
                        if (count($keys) !== count(array_unique($keys))) {
                            $warnings[] = "{$team->name}: {$template->name} assigns a player more than once in the same rubber.";
                        }
                        if (!$format->allow_player_reuse && array_intersect($seen[$side], $keys)) {
                            $warnings[] = "{$team->name}: {$template->name} reuses a player, which this format does not allow.";
                        }
                        $seen[$side] = array_merge($seen[$side], $keys);
                        $rule = $template->gender_rule;
                        if (in_array($rule, ['male', 'female', 'mixed'], true) && $keys) {
                            $known = array_filter($genders, fn ($gender) => in_array($gender, ['male', 'female'], true));
                            if (count($known) !== count($keys)) {
                                $warnings[] = "{$team->name}: verify unrecorded player genders before publication.";
                            }
                            if (in_array($rule, ['male', 'female'], true) && array_diff($known, [$rule])) {
                                $warnings[] = "{$team->name}: {$template->name} requires {$rule} players.";
                            } elseif ($rule === 'mixed' && count($known) === $template->playerCountPerTeam()
                                && (!in_array('male', $known, true) || !in_array('female', $known, true))) {
                                $warnings[] = "{$team->name}: {$template->name} requires one male and one female player.";
                            }
                        }
                    }
                    $missingSlots += $missing;
                    $rubberCount++;
                    $rubbers[] = [
                        'sequence' => $template->sequence,
                        'name' => $template->name,
                        'rubber_code' => $template->rubber_code,
                        'home_positions' => $template->home_positions,
                        'away_positions' => $template->away_positions,
                        'slots' => $slots,
                        'missing_slots' => $missing,
                    ];
                }
                $tieCount++;
                $ties[] = ['home_team' => ['id' => $home->id, 'name' => $home->name],
                    'away_team' => ['id' => $away->id, 'name' => $away->name], 'rubbers' => $rubbers];
            }
            $byes = $teams->whereNotIn('id', $playing)->map(fn ($team) => ['id' => $team->id, 'name' => $team->name])->values()->all();
            $byeCount += count($byes);
            $rounds[] = ['round_nr' => $roundNumber, 'ties' => $ties, 'byes' => $byes];
        }
        if ($missingSlots > 0) {
            $warnings[] = "{$missingSlots} player slots cannot be assigned from the current rosters.";
        }
        return [
            'format_id' => $format?->id, 'format_name' => $format?->name,
            'team_count' => $teams->count(), 'round_count' => count($rounds),
            'tie_count' => $tieCount, 'rubber_count' => $rubberCount,
            'missing_slots' => $missingSlots, 'bye_count' => $byeCount,
            'ready' => $warnings === [], 'warnings' => array_values(array_unique($warnings)), 'rounds' => $rounds,
        ];
    }
}
