<?php

namespace App\Services\Performance;

use App\Models\{CategoryResult, Event, Player, Registration};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Event-date reconstructions built explicitly off-request; pages only read saved rows. */
class PlayerEventRatingHistory
{
    public function __construct(private PlayerSharedAbilityService $ability, private PlayerEventRatingHistoryStore $store) {}

    public function forPlayer(Player $player, int $page = 1): array
    {
        $empty = ['events' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'reason' => 'Past-event ratings have not been prepared yet. Request a background update to trace this player’s recorded history.'];
        $saved = $this->store->read((int) $player->id);
        if (!$saved) return $empty;
        try { $current = $this->ability->fingerprint(); }
        catch (\Throwable) { return array_replace($empty, ['reason' => 'Past-event ratings are temporarily unavailable while their sources cannot be verified.']); }
        if (!hash_equals($saved['source_fingerprint'], $current)) return array_replace($empty, ['reason' => 'Past-event ratings need an update because their results, publication or source connection changed.']);
        $rows = array_reverse($saved['events']); $total = count($rows); $pages = max(1, (int) ceil($total / 10)); $page = max(1, min($page, $pages));
        return ['events' => array_slice($rows, ($page - 1) * 10, 10), 'total' => $total, 'page' => $page, 'pages' => $pages,
            'as_of' => $saved['as_of'], 'built_at' => $saved['built_at'], 'reason' => null];
    }

    public function build(Player $player, CarbonImmutable $asOf): array
    {
        $started = microtime(true); $fingerprint = $this->ability->fingerprint();
        $events = $this->events($player, $asOf)->get();
        if ($events->count() > PlayerEventRatingHistoryStore::MAX_EVENTS) throw new \OverflowException('This player exceeds the 200-event reconstruction limit.');
        $sources = []; $snapshots = []; $rows = []; $playedEvents = array_fill_keys(array_keys(BallRatingModel::LABELS), []);
        foreach ($events as $event) {
            if (microtime(true) - $started > 900) throw new \OverflowException('Event history exceeded its processing time limit.');
            $date = CarbonImmutable::parse($event->end_date, 'Africa/Johannesburg')->startOfDay();
            $key = $date->toDateString();
            if (!isset($snapshots[$key])) {
                $snapshots = [$key => $this->ability->historySnapshot($date, $sources)];
            }
            $snapshot = $snapshots[$key];
            if ($snapshot['reason'] ?? null) throw new \RuntimeException('Historical sources could not produce a complete reconstruction.');
            $counts = array_fill_keys(array_keys(BallRatingModel::LABELS), 0); $seen = [];
            // Use the validated, date-filtered fitted edges, including their resolved classification.
            foreach ($snapshot['all_age']['history_evidence']['matches'] ?? [] as $match) {
                if ((int) ($match['event_id'] ?? 0) !== (int) $event->id || ($match['confidence_date'] ?? '9999') > $key
                    || !in_array((int) $player->id, [(int) ($match['winner'] ?? 0), (int) ($match['loser'] ?? 0)], true)) continue;
                $source = $match['source_id'] ?? null;
                if (!is_string($source)) continue;
                if (isset($seen[$source])) continue;
                $stage = BallRatingModel::forMatch($match);
                if ($stage === null) continue;
                $seen[$source] = true; $counts[$stage]++; $playedEvents[$stage][$event->id] = true;
            }
            $levels = [];
            foreach (BallRatingModel::LABELS as $stage => $label) {
                $graph = $snapshot['ball_ratings'][$stage] ?? [];
                $hasFinish = collect($graph['history_evidence']['fields'] ?? [])->contains(fn ($field) => (int) $field['event_id'] === (int) $event->id && in_array((int) $player->id, $field['players'], true));
                $rating = $graph['ratings'][$player->id] ?? null;
                $component = $graph['components'][$rating['component'] ?? ''] ?? null;
                $value = $rating['score'] ?? null;
                $valid = $component && ($component['converged'] ?? false) === true && is_numeric($value)
                    && is_finite((float) $value) && $value >= 0 && $value <= 100;
                $levels[$stage] = ['score' => $valid ? (float) $value : null,
                    'local' => $valid && ($rating['connection_status'] ?? null) === 'disconnected',
                    'confidence_label' => $valid && in_array($rating['confidence_label'] ?? null, ['Low', 'Medium', 'High'], true) ? $rating['confidence_label'] : null,
                    'evidence_basis' => $valid ? ($rating['evidence_basis'] ?? 'matches') : null,
                    'matches_in_event' => $counts[$stage], 'cape_events' => count($playedEvents[$stage]),
                    'reason' => $valid ? ($hasFinish ? 'Includes weaker published finishing-order evidence from this event; placings are not played matches.' : ($counts[$stage] ? 'Reconstructed using eligible results available by this date.' : 'Earlier results at this level; no eligible match at this level in this event.'))
                        : ($graph['unrated_reason'] ?? 'No valid rating evidence was available at this level by this date.')];
            }
            $entryStages = $this->entryStages($event, $player);
            foreach ($counts as $stage => $count) if ($count > 0) $entryStages[$stage] = true;
            $finishes = $this->finishes($event, $player);
            $rows[] = ['event_id' => (int) $event->id, 'event' => $event->name, 'date' => $key,
                'entry_stages' => array_keys($entryStages), 'finishes' => $finishes,
                'has_eligible_matches' => array_sum($counts) > 0, 'levels' => $levels];
        }
        if (!hash_equals($fingerprint, $this->ability->fingerprint())) throw new \RuntimeException('Historical sources changed during reconstruction.');
        return ['version' => PlayerEventRatingHistoryStore::VERSION, 'player_id' => (int) $player->id, 'policy_hash' => $this->store->policyHash(),
            'source_fingerprint' => $fingerprint, 'as_of' => $asOf->toDateString(), 'built_at' => CarbonImmutable::now('Africa/Johannesburg')->toIso8601String(), 'events' => $rows];
    }

    private function finishes(Event $event, Player $player): array
    {
        if (!$event->results_published) return [];
        $registrations = Registration::query()->select('id')->whereHas('players', fn ($query) => $query->whereKey($player->id));
        $rows = CategoryResult::query()->with('category')->where('event_id', $event->id)->whereIn('registration_id', $registrations)->limit(257)->get();
        if ($rows->count() > 256) throw new \OverflowException('Published finishing results exceed their bounded limit.');
        $allowed = $event->categoryEvents()->whereIn('category_id', $rows->pluck('category_id'))->limit(257)->pluck('category_id')->all();
        $finishes = [];
        foreach ($rows->groupBy('category_id') as $category => $results) {
            if (!in_array((int) $category, array_map('intval', $allowed), true) || $results->pluck('position')->unique()->count() !== 1) continue;
            $position = filter_var($results->first()->position, FILTER_VALIDATE_INT);
            if (!$results->first()->category || $position === false || $position < 1 || $position > 256) continue;
            $finishes[] = ['category' => $results->first()->category->name, 'position' => $position];
        }
        return $finishes;
    }

    private function entryStages(Event $event, Player $player): array
    {
        $registrations = Registration::query()->select('id')->whereHas('players', fn ($query) => $query->whereKey($player->id));
        $fields = $event->categoryEvents()->with('category')->whereHas('categoryEventRegistrations', fn ($query) => $query->whereIn('registration_id', $registrations))->limit(257)->get();
        if ($fields->count() > 256) throw new \OverflowException('Event participation exceeds its bounded category limit.');
        $stages = [];
        foreach ($fields as $field) {
            $stage = BallRatingModel::forMatch(['category' => $field->category?->name, 'event_name' => $event->name]);
            if ($stage !== null) $stages[$stage] = true;
        }
        if (!$fields->count()) {
            $stage = BallRatingModel::bucket(null, $event->name);
            if ($stage !== null) $stages[$stage] = true;
        }
        return $stages;
    }

    protected function events(Player $player, CarbonImmutable $asOf): Builder
    {
        $registrations = Registration::query()->select('id')->whereHas('players', fn ($query) => $query->whereKey($player->id));
        return Event::query()->where('published', true)->whereNotNull('start_date')->whereNotNull('end_date')->whereColumn('end_date', '>=', 'start_date')
            ->whereDate('end_date', '<=', $asOf->toDateString())->where(function ($query) use ($registrations, $player) {
                $query->whereExists(fn ($results) => $results->selectRaw('1')->from('category_results')->whereColumn('category_results.event_id', 'events.id')->whereIn('registration_id', $registrations))
                    ->orWhereExists(fn ($members) => $members->selectRaw('1')->from('category_event_registrations')->join('category_events', 'category_events.id', '=', 'category_event_registrations.category_event_id')->whereColumn('category_events.event_id', 'events.id')->whereIn('registration_id', $registrations))
                    ->orWhereHas('draws', fn ($draws) => $draws->whereHas('drawFixtures', fn ($fixtures) => $fixtures->where(fn ($q) => $q->whereIn('registration1_id', $registrations)->orWhereIn('registration2_id', $registrations)))
                        ->orWhereHas('fixtures', fn ($fixtures) => $fixtures->whereHas('fixturePlayers', fn ($players) => $players->where('team1_id', $player->id)->orWhere('team2_id', $player->id)
                            ->orWhereHas('noProfile1', fn ($linked) => $linked->where('player_profile', $player->id))->orWhereHas('noProfile2', fn ($linked) => $linked->where('player_profile', $player->id)))));
            })->orderBy('end_date')->orderBy('id')->limit(PlayerEventRatingHistoryStore::MAX_EVENTS + 1);
    }
}
