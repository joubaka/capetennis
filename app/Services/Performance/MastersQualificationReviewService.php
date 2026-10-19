<?php

namespace App\Services\Performance;

use App\Models\{Event, Player};
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Private, read-only planning projection. Never a qualification decision or rating calculation. */
class MastersQualificationReviewService
{
    public const DIVISIONS = ['u10 boys', 'u10 girls', 'u11 boys', 'u11 girls', 'u12 boys', 'u12 girls', 'u13 boys', 'u13 girls'];
    private const MAX_EVENTS = 20;
    private const MAX_SOURCE_ROWS = 1000;
    private const MAX_EVENT_FIXTURES = 1000;

    public function build(string $selected, CarbonImmutable $qualificationDate, int $page = 1): array
    {
        abort_unless(auth()->user()?->hasRole('super-user'), 403);
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        $summary = [];
        foreach (self::DIVISIONS as $cohort) {
            $data = $snapshot['cohorts'][$cohort] ?? [];
            $summary[$cohort] = ['players' => count($data['ratings'] ?? []), 'groups' => count($data['components'] ?? [])];
        }
        $data = $snapshot['reason'] ? [] : ($snapshot['cohorts'][$selected] ?? []);
        $groups = []; $ids = [];
        // Rank complete saved components before pagination. Never filter/reindex by event or eligibility.
        foreach ($data['components'] ?? [] as $key => $component) {
            $members = array_values($component['members']);
            usort($members, fn ($a, $b) => (($data['ratings'][$b]['score'] ?? -INF) <=> ($data['ratings'][$a]['score'] ?? -INF))
                ?: strnatcasecmp($snapshot['names'][$a] ?? '', $snapshot['names'][$b] ?? '') ?: ($a <=> $b));
            $tenth = $members[9] ?? null;
            $tenthScore = $tenth !== null && isset($data['ratings'][$tenth]['score']) ? round((float) $data['ratings'][$tenth]['score'], 1) : null;
            $tied = $tenthScore === null ? [] : array_values(array_filter($members, fn ($id) => isset($data['ratings'][$id]['score']) && round((float) $data['ratings'][$id]['score'], 1) === $tenthScore));
            $rows = [];
            foreach ($members as $offset => $id) {
                $position = $offset + 1;
                if ($position > 12 && !(count($tied) > 1 && in_array($id, $tied, true))) { continue; }
                $rows[] = ['id' => (int) $id, 'name' => $snapshot['names'][$id] ?? 'Player #'.$id,
                    'position' => $position, 'score' => $data['ratings'][$id]['score'] ?? null,
                    'top10' => $position <= 10, 'band' => $position >= 8 && $position <= 12,
                    'boundary_tie' => count($tied) > 1 && in_array($id, $tied, true),
                    'last_played' => $data['players'][$id]['last_played'] ?? null,
                    'played' => $data['players'][$id]['played'] ?? 0];
            }
            $groups[] = ['id' => $key, 'members' => count($members), 'converged' => (bool) ($component['converged'] ?? false), 'rows' => $rows];
        }
        usort($groups, fn ($a, $b) => $b['members'] <=> $a['members'] ?: strcmp($a['id'], $b['id']));
        $paginator = new LengthAwarePaginator(array_slice($groups, ($page - 1) * 3, 3), count($groups), 3, $page,
            ['path' => request()->url(), 'query' => request()->query()]);
        foreach ($paginator->items() as $group) { foreach ($group['rows'] as $row) { $ids[] = $row['id']; } }
        $profiles = Player::query()->whereIn('id', $ids)->get(['id', 'name', 'surname', 'dateOfBirth', 'gender'])->keyBy('id');
        $auditIds = [];
        $paginator->setCollection($paginator->getCollection()->map(function ($group) use ($profiles, $qualificationDate, $selected, &$auditIds) {
            foreach ($group['rows'] as &$row) {
                $row += $this->eligibility($profiles->get($row['id']), $qualificationDate, $selected);
                if ($row['band'] || $row['boundary_tie']) { $auditIds[] = $row['id']; }
            }
            unset($row);
            return $group;
        }));
        $evidence = $auditIds ? $this->sourceReview(array_unique($auditIds), $snapshot) : ['rows' => [], 'notices' => [], 'events_checked' => 0, 'from' => null, 'as_of' => null];
        return ['selected' => $selected, 'qualificationDate' => $qualificationDate->toDateString(), 'snapshot' => $snapshot,
            'refreshStatus' => app(PlayerAbilityRefreshState::class)->status(),
            'summary' => $summary, 'groups' => $paginator, 'evidence' => $evidence,
            'snapshot_after_date' => ($snapshot['snapshot_as_of'] ?? '') > $qualificationDate->toDateString()];
    }

    private function eligibility(?Player $profile, CarbonImmutable $date, string $cohort): array
    {
        $flags = ['Primary-school attendance has not been verified.'];
        $age = null; $birth = null;
        if (!$profile) {
            $flags[] = 'Current player profile is missing; identity and age need review.';
        } else {
            $raw = (string) $profile->dateOfBirth;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $flags[] = 'Birth date is missing or invalid.';
            } else {
                try { $birth = CarbonImmutable::createFromFormat('!Y-m-d', $raw); } catch (\Throwable) { $birth = null; }
                if (!$birth || $birth->format('Y-m-d') !== $raw || $birth->greaterThan($date) || $birth->greaterThan(CarbonImmutable::today('Africa/Johannesburg'))) {
                    $flags[] = 'Birth date is invalid or in the future.'; $birth = null;
                }
            }
            if ($birth) {
                $age = $date->year - $birth->year;
                preg_match('/^u(\d+) (boys|girls)$/', $cohort, $parts);
                if ($age > (int) $parts[1]) { $flags[] = 'Over the division age under the provisional calendar-year rule.'; }
                elseif ($age < (int) $parts[1]) { $flags[] = 'Younger player: playing-up policy is not confirmed.'; }
                else { $flags[] = 'Age is within the division under the provisional calendar-year rule.'; }
            }
            $expected = str_ends_with($cohort, 'girls') ? ['2', 'female', 'girl', 'girls', 'f'] : ['1', 'male', 'boy', 'boys', 'm'];
            if (!in_array(strtolower(trim((string) $profile->gender)), $expected, true)) { $flags[] = 'Profile gender is missing or conflicts with this division.'; }
        }
        return ['age' => $age, 'birth_date' => $profile?->dateOfBirth, 'flags' => $flags];
    }

    private function sourceReview(array $ids, array $snapshot): array
    {
        $asOf = CarbonImmutable::today('Africa/Johannesburg'); $from = $asOf->subMonthsNoOverflow(24);
        $notices = ['Source checks cover only the visible review-band and boundary-tie players, within the last 24 months. They are not a complete results or identity audit.',
            'This is a player-history audit and may include other age divisions or doubles. A source gap does not necessarily affect the selected singles cohort. Read its category and context before drawing conclusions.'];
        $rows = []; $eventIds = []; $teamSources = []; $individualSources = []; $resultSources = [];
        $registrations = DB::table('player_registrations')->whereIn('player_id', $ids)->limit(self::MAX_SOURCE_ROWS + 1)->get(['registration_id', 'player_id']);
        if ($registrations->count() > self::MAX_SOURCE_ROWS) { $notices[] = 'Registration lookup reached its safe limit; some evidence was not checked.'; }
        $registrationIds = $registrations->take(self::MAX_SOURCE_ROWS)->pluck('registration_id')->unique()->all();
        $registrationPlayers = $registrations->groupBy('registration_id')->map(fn ($items) => $items->pluck('player_id')->all())->all();
        $teamRows = DB::table('team_fixture_players as fp')->join('team_fixtures as f', 'f.id', '=', 'fp.team_fixture_id')->join('draws as d', 'd.id', '=', 'f.draw_id')->join('events as e', 'e.id', '=', 'd.event_id')
            ->leftJoin('no_profile_team_players as n1', 'n1.id', '=', 'fp.team1_no_profile_id')->leftJoin('no_profile_team_players as n2', 'n2.id', '=', 'fp.team2_no_profile_id')
            ->whereBetween('e.start_date', [$from->toDateString(), $asOf->toDateString()])
            ->where(fn ($q) => $q->whereIn('fp.team1_id', $ids)->orWhereIn('fp.team2_id', $ids)->orWhereIn('n1.player_profile', $ids)->orWhereIn('n2.player_profile', $ids))
            ->orderByDesc('e.start_date')->orderBy('fp.id')->limit(self::MAX_SOURCE_ROWS + 1)
            ->get(['f.id', 'd.event_id', 'd.published as draw_published', 'fp.team1_id', 'fp.team2_id', 'n1.player_profile as linked1', 'n2.player_profile as linked2']);
        if ($teamRows->count() > self::MAX_SOURCE_ROWS) { $notices[] = 'Team-fixture lookup reached its safe limit; some matches were not checked.'; }
        foreach ($teamRows->take(self::MAX_SOURCE_ROWS) as $row) {
            $teamSources[$row->id] = ['event_id' => $row->event_id, 'draw_published' => $row->draw_published,
                'players' => array_values(array_intersect($ids, array_filter([$row->team1_id, $row->team2_id, $row->linked1, $row->linked2])))];
            $eventIds[] = $row->event_id;
        }
        $individual = DB::table('fixtures as f')->join('draws as d', 'd.id', '=', 'f.draw_id')->join('events as e', 'e.id', '=', 'd.event_id')
            ->whereBetween('e.start_date', [$from->toDateString(), $asOf->toDateString()])
            ->where(fn ($q) => $q->whereIn('f.registration1_id', $registrationIds)->orWhereIn('f.registration2_id', $registrationIds))
            ->orderByDesc('e.start_date')->orderBy('f.id')->limit(self::MAX_SOURCE_ROWS + 1)->get(['f.id', 'd.event_id', 'd.published as draw_published', 'f.registration1_id', 'f.registration2_id']);
        if ($individual->count() > self::MAX_SOURCE_ROWS) { $notices[] = 'Individual-fixture lookup reached its safe limit; some matches were not checked.'; }
        foreach ($individual->take(self::MAX_SOURCE_ROWS) as $row) {
            $individualSources[$row->id] = ['event_id' => $row->event_id, 'draw_published' => $row->draw_published,
                'players' => array_unique([...($registrationPlayers[$row->registration1_id] ?? []), ...($registrationPlayers[$row->registration2_id] ?? [])])];
            $eventIds[] = $row->event_id;
        }
        $results = DB::table('category_results as r')->join('events as e', 'e.id', '=', 'r.event_id')->whereIn('r.registration_id', $registrationIds)
            ->whereBetween('e.start_date', [$from->toDateString(), $asOf->toDateString()])->orderByDesc('e.start_date')->orderBy('r.id')->limit(self::MAX_SOURCE_ROWS + 1)->get(['r.event_id', 'r.category_id', 'r.registration_id']);
        if ($results->count() > self::MAX_SOURCE_ROWS) { $notices[] = 'Finishing-result lookup reached its safe limit; some fields were not checked.'; }
        foreach ($results->take(self::MAX_SOURCE_ROWS) as $row) {
            $resultSources[$row->event_id][$row->category_id] = array_unique([...($resultSources[$row->event_id][$row->category_id] ?? []), ...($registrationPlayers[$row->registration_id] ?? [])]);
            $eventIds[] = $row->event_id;
        }
        $events = Event::with('eventTypeModel')->whereIn('id', array_unique($eventIds))->orderByDesc('start_date')->orderByDesc('id')->limit(self::MAX_EVENTS + 1)->get();
        if ($events->count() > self::MAX_EVENTS) { $notices[] = 'Review is limited to the latest 20 relevant events. Older events were not checked; any additional omissions are listed below.'; }
        $history = app(PlayerPerformanceHistoryService::class); $pilot = app(PlayerPerformancePilotService::class);
        $checked = 0;
        foreach ($events->take(self::MAX_EVENTS) as $event) {
            $fixtureCount = DB::table('team_fixtures as f')->join('draws as d', 'd.id', '=', 'f.draw_id')->where('d.event_id', $event->id)->count()
                + DB::table('fixtures as f')->join('draws as d', 'd.id', '=', 'f.draw_id')->where('d.event_id', $event->id)->count();
            $fieldCount = DB::table('category_events')->where('event_id', $event->id)->count();
            if ($fixtureCount > self::MAX_EVENT_FIXTURES || $fieldCount > 64) { $notices[] = $event->name.': source scan omitted because it exceeds the safe event limit.'; continue; }
            $checked++; $seen = [];
            $record = function (string $kind, $id, array $players, string $status, string $reason, ?string $context = null, ?string $discipline = null) use (&$rows, $event) {
                if (count($rows) < 300) { $rows[] = ['event_id' => $event->id, 'event' => $event->name, 'date' => $event->start_date->toDateString(), 'kind' => $kind, 'source_id' => $id, 'players' => $players, 'status' => $status, 'reason' => $reason, 'context' => $context, 'discipline' => $discipline]; }
            };
            try {
                foreach ([['team', $history->teamMatches($event, null, $asOf), $teamSources], ['individual', $history->individualMatches($event, null, $pilot, $asOf, true), $individualSources]] as [$kind, $matches, $sources]) {
                    foreach ($matches as $match) {
                        if (!isset($sources[$match['fixture_id']]) || (int) $sources[$match['fixture_id']]['event_id'] !== (int) $event->id) { continue; }
                        $seen[$kind][$match['fixture_id']] = true;
                        $context = ($match['category'] ?? 'Unknown category').' · '.($match['shared_cohort'] ?? $match['cohort'] ?? 'Unresolved comparison cohort');
                        if ($match['reason']) { $record($kind, $match['fixture_id'], $sources[$match['fixture_id']]['players'], 'Canonical exclusion', $match['reason'], $context, $match['discipline']); }
                        elseif ($match['discipline'] === 'singles' && !in_array(($kind === 'team' ? 'team-match:' : 'match:').$match['fixture_id'], $snapshot['source_matches'] ?? [], true)) {
                            $record($kind, $match['fixture_id'], $sources[$match['fixture_id']]['players'], 'Outside saved snapshot', 'Current canonical checks pass, but this scored source is not listed in the saved rating snapshot. No refresh was requested.', $context, $match['discipline']);
                        }
                    }
                    foreach ($sources as $id => $source) {
                        if ((int) $source['event_id'] !== (int) $event->id || isset($seen[$kind][$id])) { continue; }
                        $record($kind, $id, $source['players'], 'Not assessed by canonical reader', !$event->published || !$source['draw_published']
                            ? 'Event or draw publication gate is closed; this source was not made rating-eligible.'
                            : 'No canonical rated-match row was returned (for example no scored result, unpublished tie or unsupported discipline). Inspect the source; no eligibility is inferred.');
                    }
                }
                if (!$event->results_published) {
                    foreach ($resultSources[$event->id] ?? [] as $category => $players) { $record('finishing result', $category, $players, 'Unpublished result gap', 'A saved finishing result exists, but event results publication is off. Its order and eligibility have not been approved.'); }
                } else {
                    foreach ($pilot->preview([], 'singles', $asOf, 24, null, $event->id, true)['evidence'] as $field) {
                        $players = array_intersect($ids, array_column($field['players'], 'id'));
                        if (!$players) { continue; }
                        if ($field['reason']) { $record('finishing result', $field['category_id'], array_values($players), 'Canonical exclusion', $field['reason'], $field['category'].' · '.$field['cohort'], 'singles'); }
                    }
                }
            } catch (\OverflowException) { $notices[] = $event->name.': canonical source checks exceeded a safety limit; this event audit is incomplete.'; }
        }
        if (count($rows) >= 300) { $notices[] = 'Source issue display reached 300 rows. Additional issues may not be shown.'; }
        return ['rows' => $rows, 'notices' => $notices, 'events_checked' => $checked, 'from' => $from->toDateString(), 'as_of' => $asOf->toDateString()];
    }
}
