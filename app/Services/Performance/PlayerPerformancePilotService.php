<?php

namespace App\Services\Performance;

use App\Models\CategoryEvent;
use App\Models\CategoryResult;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Read-only, deliberately uncalibrated tournament-performance preview. */
class PlayerPerformancePilotService
{
    public function preview(array $tiers, string $discipline, CarbonImmutable $asOf, int $months, ?Player $target = null, ?int $eventId = null, bool $network = false): array
    {
        $from = $asOf->subMonthsNoOverflow($months);
        $fieldQuery = CategoryEvent::query()
            ->with(['event.eventTypeModel', 'category'])
            ->when(!$target && !$network, fn ($query) => $query->whereIn('category_id', array_keys($tiers)))
            ->when($target, fn ($query) => $query->where(function ($query) use ($target) {
                $query->whereHas('categoryEventRegistrations', fn ($members) => $members->withTrashed()
                    ->whereHas('registration.players', fn ($players) => $players->where('players.id', $target->id)))
                    ->orWhereExists(fn ($results) => $results->selectRaw('1')->from('category_results')
                        ->whereColumn('category_results.event_id', 'category_events.event_id')
                        ->whereColumn('category_results.category_id', 'category_events.category_id')
                        ->whereIn('category_results.registration_id', \App\Models\Registration::query()
                            ->select('id')->whereHas('players', fn ($players) => $players->where('players.id', $target->id))));
            }))
            ->when($eventId, fn ($query) => $query->where('category_events.event_id', $eventId))
            ->whereHas('event', fn ($query) => $query
                ->when(!$target && !$network, fn ($query) => $query->where('published', true))
                ->where('results_published', true)
                ->when(!$target && !$network, fn ($query) => $query->whereDate('end_date', '>=', $from->toDateString()))
                ->when($target || $network, fn ($query) => $query->whereDate('start_date', '<=', $asOf->toDateString()), fn ($query) => $query->whereDate('end_date', '<=', $asOf->toDateString())))
            ->join('events', 'events.id', '=', 'category_events.event_id')
            ->select('category_events.*')
            ->orderByDesc('events.end_date')
            ->orderByDesc('category_events.id')
            ->when(!$target && !$network, fn ($query) => $query->limit(51));
        $fields = $target || $network ? $fieldQuery->lazy(25) : $fieldQuery->get();

        $truncated = !$target && !$network && $fields->count() > 50;
        $evidence = collect();
        $selectedFields = ($target || $network ? $fields : $fields->take(50))->unique(fn ($field) => $field->event_id.':'.$field->category_id);
        $eventDivisions = [];

        foreach ($selectedFields as $field) {
            $division = $target || $network ? $this->fieldDivision($field, $eventDivisions) : null;
            if (($target || $network) && $field->event->frontend_type_view === 'masters') {
                $division['cohort'] = 'masters · '.$division['cohort'];
            }
            $tier = $target || $network ? $division['tier'] : $tiers[$field->category_id];
            $rows = CategoryResult::query()
                ->where('event_id', $field->event_id)
                ->where('category_id', $field->category_id)
                ->whereNotExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('category_results as newer')
                    ->whereColumn('newer.event_id', 'category_results.event_id')
                    ->whereColumn('newer.category_id', 'category_results.category_id')
                    ->whereColumn('newer.registration_id', 'category_results.registration_id')
                    ->whereColumn('newer.id', '>', 'category_results.id'))
                ->with(['registration.players' => fn ($query) => $query->orderBy('players.id')->limit(3)])
                ->orderBy('position')
                ->limit(257)
                ->get();
            $memberships = $field->categoryEventRegistrations()->withTrashed()->limit(257)->get();
            $reason = $this->exclusionReason($field, $rows, $memberships, $discipline, $target !== null);
            $reason = $target || $network ? ($division['reason'] ?? $reason) : $reason;
            $date = CarbonImmutable::parse($field->event->end_date);
            if (($target || $network) && $date->greaterThan($asOf)) { $date = $asOf; }
            $weight = pow(0.5, max(0, $date->diffInDays($asOf, false)) / 180);
            $base = [
                'event_id' => $field->event_id,
                'category_id' => $field->category_id,
                'event' => $field->event->name,
                'category' => $field->category->name,
                'tier' => $tier,
                'cohort' => $division['cohort'] ?? null,
                'division_source' => $division['source'] ?? 'Explicit category selection',
                'date' => $date->toDateString(),
                'field_size' => $rows->count(),
                'unranked_entries' => $memberships->whereNotIn('registration_id', $rows->pluck('registration_id'))->count(),
                'field_capped' => $rows->count() > 256,
                'weight' => $weight,
                'reason' => $reason,
            ];

            foreach ($rows as $row) {
                if ($target && !$row->registration?->players->contains('id', $target->id)) {
                    continue;
                }
                $percentile = $reason === null
                    ? ($rows->count() - (int) $row->position) / ($rows->count() - 1)
                    : null;
                $points = $percentile === null
                    ? null
                    : ($tier === 'A' ? 50 : 0) + ($tier === 'Open' ? 100 : 50) * $percentile;
                $evidence->push($base + [
                    'position' => $row->position,
                    'points' => $points,
                    'players' => $row->registration?->players->filter(fn ($player) => !$target || $player->id === $target->id)->map(fn ($player) => [
                        'id' => $player->id,
                        'name' => trim($player->name.' '.$player->surname),
                    ])->all() ?? [],
                ]);
            }
            if ($rows->isEmpty() || ($target && !$rows->contains(fn ($row) => $row->registration?->players->contains('id', $target->id)))) {
                if ($target) {
                    $base['reason'] ??= 'No saved finishing result for this player';
                }
                $evidence->push($base + ['position' => null, 'points' => null, 'players' => []]);
            }
        }

        $players = $this->summarizePlayers($evidence);
        $fieldEvidence = $evidence->unique(fn ($row) => $row['event_id'].':'.$row['category_id']);
        $summary = [
            'included_fields' => $fieldEvidence->whereNull('reason')->count(),
            'skipped_fields' => $fieldEvidence->whereNotNull('reason')->count(),
            'scored_players' => $players->whereNotNull('score')->count(),
        ];

        return compact('players', 'evidence', 'truncated', 'summary');
    }

    /** Only explicit division markers are removed; age, gender and competition context remain distinct. */
    public function division(string $name): array
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $pattern = '/(?<![\pL\pN])(?:([ab])\s*[-–]?\s*(?:division|afdeling)|(?:division|afdeling)\s*[-–]?\s*([ab]))(?![\pL\pN])|(?<![\pL\pN])([ab])\s*$/u';
        preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER);
        $tiers = collect($matches)->map(fn ($match) => strtoupper($match[1] ?: ($match[2] ?? '') ?: ($match[3] ?? '')));
        $cohort = trim(preg_replace('/\s+/u', ' ', preg_replace($pattern, '', $normalized)), " -–");
        $reason = $tiers->count() !== 1 || $cohort === ''
            ? ($tiers->isEmpty() ? 'No explicit A/B division in category name' : 'Ambiguous A/B division or missing comparison cohort') : null;
        return ['tier' => $reason ? null : $tiers->first(), 'cohort' => $cohort, 'reason' => $reason];
    }

    public function fieldDivision(CategoryEvent $field, array &$eventDivisions): array
    {
        $division = $this->division($field->category->name);
        $division['source'] = 'Explicit category label';
        if ($division['reason'] !== 'No explicit A/B division in category name' || $division['cohort'] === '') {
            if ($division['reason'] === 'No explicit A/B division in category name' && $division['cohort'] !== '') {
                $division = ['tier' => 'Open', 'cohort' => $division['cohort'], 'reason' => null, 'source' => 'Open — no explicit division'];
            }
            return $division;
        }
        $eventDivisions[$field->event_id] ??= CategoryEvent::query()->with('category')->where('event_id', $field->event_id)->limit(257)->get()
            ->map(fn ($candidate) => $this->division($candidate->category->name));
        if ($eventDivisions[$field->event_id]->count() > 256) {
            return ['tier' => 'Open', 'cohort' => $division['cohort'], 'reason' => null, 'source' => 'Open — automatic pairing exceeds 256 categories'];
        }
        $matching = $eventDivisions[$field->event_id]
            ->filter(fn ($candidate) => $candidate['cohort'] === $division['cohort']);
        if ($matching->count() === 2
            && $matching->where('tier', 'B')->count() === 1
            && $matching->where('reason', 'No explicit A/B division in category name')->count() === 1) {
            return ['tier' => 'A', 'cohort' => $division['cohort'], 'reason' => null,
                'source' => 'A — main category paired with B'];
        }
        return ['tier' => 'Open', 'cohort' => $division['cohort'], 'reason' => null, 'source' => 'Open — no unique main/B pairing'];
    }

    public function forPlayer(Player $player, ?CarbonImmutable $asOf = null): array
    {
        return app(PlayerPerformanceHistoryService::class)->forPlayer($player, $asOf ?? CarbonImmutable::today(), $this);
    }

    private function exclusionReason(CategoryEvent $field, Collection $rows, Collection $memberships, string $discipline, bool $historical = false): ?string
    {
        if ($field->event->isTeam() || $field->event->frontend_type_view === 'team') {
            return 'Team-event results are excluded from this pilot';
        }
        if (CategoryEvent::query()->where('event_id', $field->event_id)->where('category_id', $field->category_id)->count() !== 1) {
            return 'Multiple category definitions for this event make the field ambiguous';
        }
        if ($rows->count() > 256 || $memberships->count() > 256) {
            return 'Field exceeds the 256-entry preview limit';
        }
        $positions = $rows->pluck('position')->map(fn ($position) => (int) $position)->all();
        if ($rows->count() < 2 || $positions !== range(1, $rows->count())) {
            return 'Incomplete or ambiguous finishing positions';
        }
        $rankedIds = $rows->pluck('registration_id')->sort()->values()->all();
        $memberIds = $memberships->whereIn('registration_id', $rankedIds)->pluck('registration_id')->sort()->values()->all();
        if ($rankedIds !== $memberIds) {
            return 'Ranked field has missing or duplicate recorded category membership';
        }
        if ($rows->contains(fn ($row) => !$row->registration
            || $row->registration->players->count() !== ($discipline === 'singles' ? 1 : 2))) {
            return 'Registration discipline is missing or differs from the selected discipline';
        }
        $playerIds = $rows->flatMap(fn ($row) => $row->registration->players->pluck('id'));
        if ($playerIds->unique()->count() !== $playerIds->count()) {
            return 'A player appears on multiple registrations in this field';
        }

        return null;
    }

    private function summarizePlayers(Collection $evidence): Collection
    {
        $players = collect();
        foreach ($evidence as $entry) {
            foreach ($entry['players'] as $player) {
                if (!$players->has($player['id'])) {
                    $players->put($player['id'], $player + ['results' => collect()]);
                }
                $players[$player['id']]['results']->push($entry);
            }
        }

        return $players->map(function ($player) {
            $included = $player['results']->whereNull('reason');
            $denominator = $included->sum('weight');
            return $player + [
                'score' => $denominator > 0
                    ? round($included->sum(fn ($row) => $row['points'] * $row['weight']) / $denominator, 1)
                    : null,
                'count' => $included->count(),
                'last_played' => $included->max('date'),
            ];
        })->sort(fn ($a, $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1)
            ?: strcmp($a['name'], $b['name']) ?: $a['id'] <=> $b['id'])->values();
    }
}

