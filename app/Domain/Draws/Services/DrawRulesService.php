<?php

namespace App\Domain\Draws\Services;

use App\Models\Draw;

/** One saved-settings projection for the editor and every draw pack. */
final class DrawRulesService
{
    public function sections(Draw $draw): array
    {
        $settings = $draw->settings;
        $sections = [];
        $add = function (string $key, string $label, string $rules = '') use (&$sections, $settings): void {
            $sections[] = [
                'key' => $key, 'label' => $label, 'rules' => $rules,
                'notes' => $this->eventNotes($settings?->notes[$key] ?? ''),
                'print_notes' => (bool) ($settings?->notes_print[$key] ?? true),
            ];
        };
        $add('general', 'General rules');
        $format = $settings?->score_format;
        if ($format && isset(TennisScoreFormat::catalog()[$format])) {
            $add('match_format', 'Configured match format', TennisScoreFormat::get($format)['label']."\n\n".TennisScoreFormat::rules($format));
        }

        $flexible = $draw->usesFlexibleMonrad();
        $workflow = $settings?->workflow;
        $roundRobin = ! $flexible && ! in_array($workflow, ['playoffs', 'monrad', 'custom_monrad', 'feed_in_monrad'], true);
        if ($roundRobin) {
            $add('round_robin', 'Round Robin scoring rules', 'Players play the other players in their group. Standings are ordered by matches won, then percentage of sets won, then percentage of games won, then head-to-head. Players still tied remain equal until the tie is resolved.');
        }
        if ($roundRobin && ! $draw->isRoundRobinOnly()) {
            $brackets = collect($settings?->playoff_config ?? [])->filter(fn ($bracket) => is_array($bracket) && ($bracket['enabled'] ?? false));
            if ($brackets->isNotEmpty()) {
                $add('playoffs', 'Playoff rules', 'Qualifiers enter the brackets below according to their finishing position in each group. Winners advance along the draw; losers follow any placement matches shown in the bracket. Byes advance without a match.');
                foreach ($brackets as $bracket) {
                    $positions = implode(', ', array_map('intval', $bracket['positions'] ?? []));
                    $rules = "Qualifying group positions: {$positions}. Bracket size: ".(int) ($bracket['size'] ?? 0).'.';
                    if (! empty($bracket['group_order'])) {
                        $rules .= ' Group seed order: '.implode(', ', $bracket['group_order']).'.';
                    }
                    $add('bracket_'.$bracket['slug'], ($bracket['name'] ?? 'Bracket').' rules', $rules);
                }
            }
        } elseif (! $roundRobin) {
            $mode = $draw->flexibleMonrad?->draft['mode'] ?? $workflow;
            $add('playoffs', $mode === 'playoffs' ? 'Playoff rules' : 'Placement draw rules', $mode === 'playoffs'
                ? 'Winners advance through the knockout bracket to the final. Byes advance without a match.'
                : ($mode === 'feed_in_monrad'
                    ? 'Main-draw winners play for the title. Earlier-round losers cross into the feed-in draw and meet later-round losers for fifth place; semifinal losers play for third. Further placement matches determine the remaining positions. Byes advance without a match.'
                    : 'Winners and losers follow the pathways shown in the draw to determine final positions. Byes advance without a match.'));
            if ($mode === 'feed_in_monrad' && ($draw->flexibleMonrad?->draft['challenge_playoffs'] ?? false)) {
                $add('challenges', 'Optional challenge play-offs', 'After every base match is complete, fixed base positions 2 v 3, 4 v 5, 6 v 7 and so on may play once more. Pairs who already played each other in this draw skip the challenge and keep their base order. A played challenge winner takes the higher position and its loser the lower; first and the last unpaired position remain unchanged. Positions are provisional until all eligible challenges are complete.');
            }
        }

        return $sections;
    }

    public function printable(Draw $draw): array
    {
        $notes = [];
        foreach ($this->sections($draw) as $section) {
            $text = trim($section['rules'].($section['print_notes'] && $section['notes'] !== '' ? "\n\n".$section['notes'] : ''));
            if ($text !== '') {
                $label = $section['label'];
                if (array_key_exists($label, $notes)) {
                    $label .= ' ('.$section['key'].')';
                }
                $notes[$label] = $text;
            }
        }
        return $notes;
    }

    private function eventNotes(?string $note): string
    {
        $note = trim(str_replace("\r\n", "\n", $note ?? ''));
        $legacyWarning = 'Legacy scoring is active. Select an enforceable Match Scoring Format in Settings before publishing these rules.';
        $defaults = ["General Rules\n\nPlayers must be ready to play at their scheduled time.\nA 5-minute warm-up is allowed before the match starts.\nStandard ITF tennis rules apply unless otherwise specified by the tournament organizer.\nThe tournament referee's decision is final in all disputes."];
        foreach (['Round Robin Match Format', 'Playoff Match Format', 'Bracket Match Format'] as $heading) {
            foreach (array_merge([$legacyWarning], array_column(TennisScoreFormat::catalog(), 'rules')) as $rules) {
                $defaults[] = $heading."\n\n".$rules;
            }
        }

        return in_array($note, $defaults, true) ? '' : $note;
    }
}
