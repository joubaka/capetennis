<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture};
use App\Services\IndividualMatchOutcomeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Fixed four-group trials playoff. Repeated pairings reference the first result. */
final class InterproRoundRobinFormat
{
    public const PRESET = 'interpro_rr_feed_in';

    public static function selected(Draw $draw): bool
    {
        return $draw->settings?->workflow === 'round_robin_playoffs'
            && $draw->settings?->preset_key === self::PRESET;
    }

    public static function configuration(): array
    {
        return [['name' => 'Interpro trials — first result stands', 'slug' => 'MAIN',
            'size' => 4, 'positions' => [1], 'enabled' => true, 'trials_carries' => []]];
    }

    /** Topological order is deliberate; source references never depend on fixture IDs. */
    public static function template(?Draw $draw = null): array
    {
        $template = [
            2001 => ['MAIN', 1, ['A1', 'D1']], 2002 => ['MAIN', 1, ['B1', 'C1']],
            3001 => ['PLATE', 1, ['A2', 'D3']], 3002 => ['PLATE', 1, ['B2', 'C3']],
            3003 => ['PLATE', 1, ['C2', 'B3']], 3004 => ['PLATE', 1, ['D2', 'A3']],
            2003 => ['MAIN', 2, ['W2001', 'W2002'], 1],
            3005 => ['PLATE', 2, ['W3001', 'W3002']], 3006 => ['PLATE', 2, ['W3003', 'W3004']],
            4001 => ['CONS', 2, ['L3001', 'L3002']], 4002 => ['CONS', 2, ['L3003', 'L3004']],
            3007 => ['PLATE', 3, ['L2002', 'W3005']], 3008 => ['PLATE', 3, ['L2001', 'W3006']],
            3010 => ['PLATE', 3, ['L3005', 'L3006'], 7],
            4003 => ['CONS', 3, ['W4001', 'W4002'], 9],
            4004 => ['CONS', 3, ['L4001', 'L4002'], 11],
            3009 => ['PLATE', 4, ['W3007', 'W3008'], 3],
            3011 => ['PLATE', 4, ['L3007', 'L3008'], 5],
        ];
        $terminals = [1 => 2003, 3 => 3009, 5 => 3011, 7 => 3010, 9 => 4003, 11 => 4004];
        foreach (self::challengePairs($draw) as $upper) {
            $template[5000 + $upper] = ['CHALLENGE', 5,
                ['L'.$terminals[$upper - 1], 'W'.$terminals[$upper + 1]], $upper];
        }
        return $template;
    }

    public static function challengePairs(?Draw $draw): array
    {
        $config = $draw?->settings?->playoff_config ?? [];
        if (!($config['challenge_playoffs'] ?? false)) return [];
        return array_values(array_intersect([2, 4, 6, 8, 10], array_map('intval', $config['challenge_pairs'] ?? [])));
    }

    public function prepare(Draw $draw): Collection
    {
        return DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            if (!self::selected($draw) || $draw->locked) return collect();
            $groups = $draw->groups()->withCount('groupRegistrations')->orderBy('name')->get();
            abort_unless($draw->event?->isInterprovincialTrials() && $groups->pluck('name')->all() === ['A', 'B', 'C', 'D']
                && $groups->every(fn ($group) => $group->group_registrations_count >= 3 && $group->group_registrations_count <= 4),
                422, 'Interpro trials require four groups A–D with three or four players each.');
            $existing = $draw->drawFixtures()->where('stage', '!=', 'RR')->get();
            if ($existing->isNotEmpty()) {
                abort_unless($existing->count() === count(self::template($draw))
                    && $existing->pluck('match_nr')->sort()->values()->all() === collect(array_keys(self::template($draw)))->sort()->values()->all(),
                    409, 'Existing playoff fixtures do not match the selected Interpro format. Review the draw before continuing.');
                return collect();
            }
            abort_if($draw->published || $draw->oop_published, 409, 'Prepare the trial playoffs before publishing the draw or its schedule.');
            $fixtures = collect();
            foreach (self::template($draw) as $number => [$stage, $round, $sources]) {
                $attributes = ['draw_id' => $draw->id, 'match_nr' => $number, 'stage' => $stage, 'round' => $round,
                    'position' => self::template($draw)[$number][3] ?? null];
                foreach ($sources as $slot => $source) {
                    if (preg_match('/^([A-D])([1-3])$/', $source, $match)) {
                        $attributes['registration'.($slot + 1).'_source_group_id'] = $groups->firstWhere('name', $match[1])->id;
                        $attributes['registration'.($slot + 1).'_source_position'] = (int) $match[2];
                    }
                }
                $fixtures->put($number, Fixture::create($attributes));
            }
            foreach (self::template($draw) as $number => $definition) {
                foreach ($definition[2] as $source) {
                    if (preg_match('/^([WL])(\d+)$/', $source, $match)) {
                        $fixtures[(int) $match[2]]->update([$match[1] === 'W' ? 'parent_fixture_id' : 'loser_parent_fixture_id' => $fixtures[$number]->id]);
                    }
                }
            }
            DrawAuditLog::record($draw->id, 'interpro_playoffs_prepared', null, ['fixture_ids' => $fixtures->pluck('id')->all()]);
            return $fixtures->values();
        });
    }

    /** Resolve only after the normal reviewed group progression has been confirmed. */
    public function resolve(Draw $draw, ?array $standings = null, bool $confirm = false): int
    {
        return DB::transaction(function () use ($draw, $standings, $confirm) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            if (!self::selected($draw) || $draw->locked) return 0;
            $config = $draw->settings->playoff_config;
            if (!$confirm && empty($config[0]['trials_confirmed'])) return 0;
            $fixtures = $draw->drawFixtures()->with(['fixtureResults', 'draw.settings'])->lockForUpdate()->get();
            $byNumber = $fixtures->where('stage', '!=', 'RR')->keyBy('match_nr');
            abort_unless($byNumber->count() === count(self::template($draw)), 409, 'Prepare the complete Interpro playoff before progressing.');
            foreach ($byNumber as $fixture) abort_if(self::hasReference($fixture) && !self::source($fixture), 409,
                'A carried trial result changed or is unavailable. Review its original result before progressing.');
            $standings ??= app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw);
            if ($confirm) $this->assertGroupsReady($draw, $standings);
            $groups = $draw->groups()->get()->keyBy('name');
            $changed = 0;
            $earlier = $fixtures->where('stage', 'RR')->values();
            $outcomes = app(IndividualMatchOutcomeService::class);
            foreach (self::template($draw) as $number => $definition) {
                $fixture = $byNumber[$number];
                if ($definition[0] === 'CHALLENGE' && $byNumber->where('stage', '!=', 'CHALLENGE')->contains(
                    fn ($base) => (int) $base->match_status !== 5 && !$outcomes->winner($base))) continue;
                $absent = [];
                foreach ($definition[2] as $slot => $source) {
                    $registration = null;
                    if (preg_match('/^([A-D])([1-3])$/', $source, $match)) {
                        $row = collect($standings[$groups[$match[1]]->id] ?? [])->values()->get((int) $match[2] - 1);
                        $registration = $row['reg_id'] ?? null;
                    } elseif (preg_match('/^([WL])(\d+)$/', $source, $match)) {
                        $previous = $byNumber[(int) $match[2]];
                        $winner = $outcomes->winner($previous);
                        $registration = !$winner ? null : ($match[1] === 'W' ? $winner
                            : ((int) $previous->registration1_id === $winner ? $previous->registration2_id : $previous->registration1_id));
                        if ((int) $previous->match_status === 5 || ($winner && !$registration)) $absent[] = $slot;
                    }
                    $column = 'registration'.($slot + 1).'_id';
                    abort_if($fixture->$column && (int) $fixture->$column !== (int) $registration, 409,
                        'A confirmed trial source changed. Review the original result through draw recovery.');
                    if ($registration && !$fixture->$column) { $fixture->$column = $registration; $changed++; }
                }
                if ($absent && $fixture->fixtureResults->isEmpty() && ($fixture->registration1_id || in_array(0, $absent, true))
                    && ($fixture->registration2_id || in_array(1, $absent, true))) {
                    $winner = $fixture->registration1_id ?: $fixture->registration2_id;
                    $config[0]['trials_advances'][$fixture->id] = $winner ? (int) $winner : 0;
                    $fixture->winner_registration = $winner;
                    $fixture->match_status = $winner ? 0 : 5;
                    $fixture->setRelation('draw', $draw);
                    $draw->settings->playoff_config = $config;
                }
                if ($fixture->registration1_id && $fixture->registration2_id && $fixture->fixtureResults->isEmpty()) {
                    $pair = self::pair($fixture);
                    $prior = $earlier->filter(fn ($candidate) => self::pair($candidate) === $pair)
                        ->sortBy(fn ($candidate) => $candidate->stage === 'RR' ? 0 : array_search((int) $candidate->match_nr, array_keys(self::template($draw)), true) + 1)->first();
                    if ($prior && $fixture->stage === 'CHALLENGE') {
                        $config[0]['trials_skipped_challenges'][$fixture->id] = (int) $fixture->registration1_id;
                        $config[0]['trials_advances'][$fixture->id] = (int) $fixture->registration1_id;
                        $fixture->winner_registration = $fixture->registration1_id;
                        $fixture->setRelation('draw', $draw);
                        $draw->settings->playoff_config = $config;
                        $bookings = $fixture->orderOfPlay()->get();
                        if ($bookings->isNotEmpty()) {
                            DrawAuditLog::record($draw->id, 'interpro_skipped_challenge_booking_released', $fixture->id,
                                ['previous_assignments' => $bookings->map(fn ($booking) => $booking->getAttributes())->all()]);
                        }
                        $fixture->orderOfPlay()->delete();
                        DB::table('schedules')->where('draw_id', $draw->id)->where('fixture_id', $fixture->id)->delete();
                        $fixture->scheduled = 0;
                    } elseif ($prior) {
                        $winner = $outcomes->winner($prior);
                        abort_unless($winner, 409, 'The first meeting has no decisive result. Resolve it before continuing this trial branch.');
                        $original = self::source($prior) ?? $prior;
                        $config[0]['trials_carries'][$fixture->id] = ['source' => $original->id, 'fingerprint' => self::fingerprint($original)];
                        $bookings = $fixture->orderOfPlay()->get();
                        if ($bookings->isNotEmpty()) {
                            DrawAuditLog::record($draw->id, 'interpro_carried_booking_released', $fixture->id,
                                ['source_fixture_id' => $original->id, 'previous_assignments' => $bookings->map(fn ($booking) => $booking->getAttributes())->all()]);
                            $fixture->orderOfPlay()->delete();
                            $fixture->scheduled = 0;
                        }
                        DB::table('schedules')->where('draw_id', $draw->id)->where('fixture_id', $fixture->id)->delete();
                        $fixture->winner_registration = $winner;
                        // No played status or set records: this is a progression reference only.
                        $fixture->match_status = 0;
                        $fixture->setRelation('draw', $draw);
                        $draw->settings->playoff_config = $config;
                    }
                }
                if ($fixture->isDirty()) $fixture->save();
                $earlier->push($fixture);
            }
            if ($confirm) $config[0]['trials_confirmed'] = true;
            $draw->settings->update(['playoff_config' => $config]);
            return $changed;
        });
    }

    public static function source(Fixture $fixture): ?Fixture
    {
        $draw = $fixture->draw;
        if (!$draw || !self::selected($draw)) return null;
        $reference = $draw->settings->playoff_config[0]['trials_carries'][$fixture->id] ?? null;
        if (!$reference) return null;
        $source = $draw->drawFixtures()->with(['fixtureResults', 'draw.settings'])->find($reference['source']);
        if (!$source || (int) $source->id === (int) $fixture->id || self::pair($source) !== self::pair($fixture)
            || $source->fixtureResults->isEmpty() && !$source->isWalkover() && !$source->isRetired()
            || !hash_equals($reference['fingerprint'], self::fingerprint($source))) return null;
        $order = array_flip(array_keys(self::template($draw)));
        if (!isset($order[(int) $fixture->match_nr]) || ($source->stage !== 'RR'
            && (!isset($order[(int) $source->match_nr]) || $order[(int) $source->match_nr] >= $order[(int) $fixture->match_nr]))) return null;
        return $source;
    }

    public static function hasReference(Fixture $fixture): bool
    {
        return $fixture->draw && self::selected($fixture->draw)
            && isset($fixture->draw->settings->playoff_config[0]['trials_carries'][$fixture->id]);
    }

    public static function noMatchRequired(Fixture $fixture): bool
    {
        return $fixture->draw && self::selected($fixture->draw) && (self::hasReference($fixture)
            || (int) $fixture->match_status === 5
            || array_key_exists($fixture->id, $fixture->draw->settings->playoff_config[0]['trials_advances'] ?? []));
    }

    public static function automaticWinner(Fixture $fixture): ?int
    {
        if (!$fixture->draw || !self::selected($fixture->draw)) return null;
        $winner = (int) ($fixture->draw->settings->playoff_config[0]['trials_advances'][$fixture->id] ?? 0);
        return $winner && !$fixture->fixtureResults->count() && (!$fixture->registration1_id || !$fixture->registration2_id
                || isset($fixture->draw->settings->playoff_config[0]['trials_skipped_challenges'][$fixture->id]))
            && in_array($winner, [(int) $fixture->registration1_id, (int) $fixture->registration2_id], true) ? $winner : null;
    }

    public static function pair(Fixture $fixture): string
    {
        $ids = [(int) $fixture->registration1_id, (int) $fixture->registration2_id]; sort($ids);
        return implode(':', $ids);
    }

    private static function fingerprint(Fixture $fixture): string
    {
        return hash('sha256', json_encode([$fixture->only(['id', 'draw_id', 'registration1_id', 'registration2_id', 'winner_registration', 'match_status']),
            $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->only(['id', 'set_nr', 'registration1_score', 'registration2_score']))->values()->all()]));
    }

    public static function assertScoreMutable(Fixture $fixture): void
    {
        if (!self::selected($fixture->draw)) return;
        $config = $fixture->draw->settings->playoff_config[0] ?? [];
        abort_if(array_key_exists($fixture->id, $config['trials_advances'] ?? []), 409, 'This trial position is resolved without a new match.');
        abort_if($fixture->stage !== 'RR' && (empty($config['trials_confirmed']) || !$fixture->registration1_id || !$fixture->registration2_id),
            409, 'Confirm the trial group standings and both participants before scoring this playoff.');
        abort_if(isset($config['trials_carries'][$fixture->id]), 409, 'First result stands. This pairing uses its original result and must not be scored again.');
        abort_if($fixture->stage === 'RR' && !empty($config['trials_confirmed']), 409,
            'Trial group positions have been confirmed. Review corrections through draw recovery before changing the group results.');
        abort_if(collect($config['trials_carries'] ?? [])->contains(fn ($reference) => (int) $reference['source'] === (int) $fixture->id), 409,
            'This original result is carried into a later trial match. Review the dependent draw before correcting it.');
        abort_if($fixture->stage !== 'RR' && !empty($config['trials_confirmed'])
            && ($fixture->fixtureResults()->exists() || $fixture->isWalkover() || $fixture->isRetired()), 409,
            'A completed Interpro playoff result cannot be overwritten or cleared after progression. Review its dependent trial routes first.');
    }

    public static function score(Fixture $fixture): ?string
    {
        $source = self::source($fixture);
        if (isset($fixture->draw?->settings?->playoff_config[0]['trials_skipped_challenges'][$fixture->id])) {
            return 'Challenge skipped · already played · base positions retained';
        }
        if (!$source) return self::hasReference($fixture) ? 'Original result changed · review required'
            : (self::automaticWinner($fixture) ? 'No match required · opponent withdrawn' : null);
        $reverse = (int) $source->registration1_id !== (int) $fixture->registration1_id;
        $score = $source->isWalkover() ? 'W/O' : $source->fixtureResults->sortBy('set_nr')->map(fn ($set) => $reverse
            ? $set->registration2_score.'-'.$set->registration1_score : $set->registration1_score.'-'.$set->registration2_score)->implode(', ');
        return $score.' · First result stands (match '.$source->match_nr.')';
    }

    public function assertGroupsReady(Draw $draw, ?array $standings = null): void
    {
        $groups = $draw->groups()->with('groupRegistrations')->orderBy('name')->get();
        abort_unless($draw->event?->isInterprovincialTrials() && $groups->pluck('name')->all() === ['A', 'B', 'C', 'D']
            && $groups->every(fn ($group) => $group->groupRegistrations->count() >= 3 && $group->groupRegistrations->count() <= 4),
            422, 'Interpro trials require four groups A–D with three or four players each.');
        $ids = $groups->flatMap(fn ($group) => $group->groupRegistrations->pluck('registration_id'));
        abort_unless($ids->count() === $ids->unique()->count(), 422, 'A trial player may belong to only one group.');
        $standings ??= app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw);
        $service = app(TwoStageRoundRobinService::class);
        foreach ($groups as $group) {
            $fixtures = $draw->drawFixtures()->where('stage', 'RR')->where('draw_group_id', $group->id)->with('fixtureResults')->get();
            abort_unless($service->complete($draw, $group, $fixtures)
                && $service->positionsResolved($draw, array_values($standings[$group->id] ?? []), $fixtures),
                422, 'Complete the full trial group programme and resolve tied finishing positions before progressing.');
            abort_if($fixtures->contains(fn ($fixture) => !$fixture->isRoundRobinVoided() && app(IndividualMatchOutcomeService::class)->winner($fixture) === null),
                422, 'First result stands requires a decisive original result for every group match. Resolve drawn matches before confirming trial progression.');
        }
    }
}
