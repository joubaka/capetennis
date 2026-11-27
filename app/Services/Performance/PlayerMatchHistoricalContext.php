<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Schema};

/** Raw historical dependencies only. No rating or match reconstruction on reads. */
class PlayerMatchHistoricalContext
{
    public const VERSION = 1;
    protected const MAX_ROWS = 250000;
    protected const MAX_BYTES = 33554432;
    private const COLUMNS = [
        'category_events' => ['id', 'event_id', 'category_id'],
        'draws' => ['id', 'event_id', 'category_event_id', 'drawName', 'published', 'team_scoring_rules', 'team_draw_selection'],
        'fixtures' => ['id', 'draw_id', 'registration1_id', 'registration2_id', 'match_status', 'winner_registration', 'stage'],
        'team_ties' => ['id', 'draw_id', 'home_team_id', 'away_team_id', 'published_at', 'status'],
        'teams' => ['id', 'name', 'year', 'region_id', 'category_event_id'],
        'team_players' => ['id', 'team_id', 'player_id', 'rank'],
        'no_profile_team_players' => ['id', 'team_id', 'player_profile', 'rank'],
        'team_fixture_players' => ['id', 'team_fixture_id', 'team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id', 'participant_snapshot'],
        'team_fixtures' => ['id', 'draw_id', 'team_tie_id', 'fixture_type', 'numSets', 'scheduled_at', 'venue_id', 'court_label', 'region1', 'region2', 'rubber_code', 'match_status', 'age'],
        'event_regions' => ['id', 'event_id', 'region_id'],
        'category_event_registrations' => ['id', 'category_event_id', 'registration_id', 'status', 'withdrawn_at', 'deleted_at'],
        'draw_settings' => ['id', 'draw_id', 'workflow', 'num_sets', 'score_format', 'playoff_score_format', 'require_full_sets'],
        'categories' => ['id', 'name'], 'eventtypes' => ['id', 'name', 'code', 'type'], 'team_regions' => ['id', 'region_name'],
    ];

    public function capture(string $asOf): array
    {
        $key = implode(':', ['historical-match-context', static::class, self::VERSION, static::MAX_ROWS, static::MAX_BYTES, $asOf]);
        return app(PlayerRatingReadScope::class)->remember($key, fn () => $this->captureFresh($asOf));
    }

    protected function captureFresh(string $asOf): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $asOf)
            || CarbonImmutable::parse($asOf)->toDateString() !== $asOf) throw new \UnexpectedValueException('Invalid historical context date.');
        $hash = hash_init('sha256'); $rows = 0; $bytes = 0;
        $append = function (string $value) use ($hash, &$bytes): void {
            $bytes += strlen($value);
            if ($bytes > static::MAX_BYTES) throw new \OverflowException('Historical context byte limit exceeded.');
            hash_update($hash, strlen($value).':'.$value);
        };
        $capture = function (string $table, ?Builder $query = null, array $columns = ['*'], bool $optional = false) use ($append, &$rows): array {
            $append($table);
            if (!Schema::hasTable($table)) {
                if (!$optional) throw new \UnexpectedValueException('Historical context source unavailable.');
                $append('absent'); return [];
            }
            $query ??= DB::table($table);
            if ($columns === ['*']) $columns = self::COLUMNS[$table] ?? $columns;
            $query->select($columns)->orderBy($table === 'player_registrations' ? 'registration_id' : 'id');
            if ($table === 'player_registrations') $query->orderBy('player_id');
            $query->limit(max(1, static::MAX_ROWS - $rows + 1));
            $result = [];
            foreach ($query->cursor() as $row) {
                if (++$rows > static::MAX_ROWS) throw new \OverflowException('Historical context row limit exceeded.');
                $raw = (array) $row;
                $append(json_encode($raw, JSON_THROW_ON_ERROR));
                $result[] = $raw;
            }
            $append('end:'.count($result)); return $result;
        };
        $ids = fn (array $records, string $column = 'id') => array_values(array_unique(array_filter(array_map(
            fn ($row) => (int) ($row[$column] ?? 0), $records))));
        $in = fn (string $table, string $column, array $values) => DB::table($table)->whereIntegerInRaw($column, array_values(array_unique($values)));

        // Include unpublished/empty historical roots: later publication and backdated additions must invalidate.
        $events = $capture('events', DB::table('events')->whereDate('start_date', '<=', $asOf),
            ['id', 'name', 'published', 'results_published', 'start_date', 'end_date', 'eventType']);
        $eventIds = $ids($events);
        $fields = $capture('category_events', $in('category_events', 'event_id', $eventIds));
        $fieldIds = $ids($fields);
        $draws = $capture('draws', $in('draws', 'event_id', $eventIds));
        $drawIds = $ids($draws);
        $fixtures = $capture('fixtures', $in('fixtures', 'draw_id', $drawIds));
        $fixtureIds = $ids($fixtures);
        $capture('fixture_results', $in('fixture_results', 'fixture_id', $fixtureIds));
        $teamFixtures = $capture('team_fixtures', $in('team_fixtures', 'draw_id', $drawIds));
        $teamFixtureIds = $ids($teamFixtures);
        $capture('team_fixture_results', $in('team_fixture_results', 'team_fixture_id', $teamFixtureIds));
        $participants = $capture('team_fixture_players', $in('team_fixture_players', 'team_fixture_id', $teamFixtureIds));
        $ties = $capture('team_ties', DB::table('team_ties')->where(function ($query) use ($drawIds, $teamFixtures, $ids) {
            $query->whereIntegerInRaw('draw_id', $drawIds)->orWhereIntegerInRaw('id', $ids($teamFixtures, 'team_tie_id'));
        }));
        $regions = $capture('event_regions', $in('event_regions', 'event_id', $eventIds));
        $recoveries = $capture('historical_identity_recoveries', $in('historical_identity_recoveries', 'event_id', $eventIds), optional: true);
        $snapshots = [];
        foreach ($participants as $participant) {
            $snapshot = $participant['participant_snapshot'] ?? null;
            if (is_string($snapshot)) $snapshot = json_decode($snapshot, true, 32, JSON_THROW_ON_ERROR);
            if (is_array($snapshot)) foreach ($snapshot as $side) if (is_array($side)) $snapshots[] = $side;
        }
        $importIds = [...$ids($participants, 'team1_no_profile_id'), ...$ids($participants, 'team2_no_profile_id')];
        $imports = $capture('no_profile_team_players', $in('no_profile_team_players', 'id', $importIds));
        $teamIds = [...$ids($ties, 'home_team_id'), ...$ids($ties, 'away_team_id'), ...$ids($snapshots, 'source_team_id'),
            ...$ids($imports, 'team_id'), ...$ids($recoveries, 'source_team_id')];
        $years = array_values(array_unique(array_map(fn ($event) => (int) substr($event['start_date'], 0, 4), $events)));
        $teams = $capture('teams', DB::table('teams')->where(function ($query) use ($teamIds, $fieldIds, $years, $regions, $ids) {
            $query->whereIntegerInRaw('id', $teamIds)->orWhereIntegerInRaw('category_event_id', $fieldIds)
                ->orWhere(fn ($legacy) => $legacy->whereNull('category_event_id')->whereIntegerInRaw('year', $years)
                    ->whereIntegerInRaw('region_id', $ids($regions, 'region_id')));
        }));
        $teamIds = $ids($teams);
        $roster = $capture('team_players', $in('team_players', 'team_id', $teamIds));
        $imports = $capture('no_profile_team_players', $in('no_profile_team_players', 'team_id', $teamIds));
        // Referenced cross-event definitions and recovery dependencies may legitimately be malformed: hash them too.
        $capture('category_events', $in('category_events', 'id', [...$ids($draws, 'category_event_id'), ...$ids($teams, 'category_event_id')]));
        $results = $capture('category_results', $in('category_results', 'event_id', $eventIds));
        $memberships = $capture('category_event_registrations', $in('category_event_registrations', 'category_event_id', $fieldIds));
        $registrationIds = [...$ids($fixtures, 'registration1_id'), ...$ids($fixtures, 'registration2_id'),
            ...$ids($results, 'registration_id'), ...$ids($memberships, 'registration_id')];
        $capture('registrations', $in('registrations', 'id', $registrationIds), ['id']);
        $members = $capture('player_registrations', $in('player_registrations', 'registration_id', $registrationIds), ['registration_id', 'player_id']);
        $playerIds = [...$ids($members, 'player_id'), ...$ids($roster, 'player_id'), ...$ids($imports, 'player_profile'),
            ...$ids($participants, 'team1_id'), ...$ids($participants, 'team2_id'), ...$ids($snapshots, 'profile_id'),
            ...$ids($snapshots, 'linked_profile_id'), ...$ids($recoveries, 'suggested_player_id')];
        $capture('players', $in('players', 'id', $playerIds), ['id', 'name', 'surname', 'dateOfBirth', 'gender']);
        $capture('draw_settings', $in('draw_settings', 'draw_id', $drawIds));
        $capture('order_of_plays', DB::table('order_of_plays')->where(fn ($query) => $query
            ->whereIntegerInRaw('fixture_id', $fixtureIds)->orWhereIntegerInRaw('draw_id', $drawIds)));
        $capture('event_venue_courts', $in('event_venue_courts', 'event_id', $eventIds), optional: true);
        $capture('draw_venue_court_allocations', $in('draw_venue_court_allocations', 'draw_id', $drawIds), optional: true);
        foreach (['categories', 'eventtypes', 'team_regions'] as $definition) $capture($definition);
        if ($recoveries) {
            $recoveryContext = app(HistoricalIdentityRecoveryService::class)->context($recoveries);
            foreach ($recoveryContext as $sourceRows) {
                $rows += count($sourceRows);
                if ($rows > static::MAX_ROWS) throw new \OverflowException('Historical recovery context row limit exceeded.');
            }
            $append(json_encode($recoveryContext, JSON_THROW_ON_ERROR));
        }
        // External API has no equivalent scoped signature; conservatively retain its complete publication/content guard.
        $append(app(TeamSheetTennisEvidence::class)->fingerprint());
        return ['version' => self::VERSION, 'as_of' => $asOf, 'fingerprint' => hash_final($hash)];
    }

    public function valid(mixed $context, string $asOf): bool
    {
        return is_array($context) && count($context) === 3 && ($context['version'] ?? null) === self::VERSION
            && ($context['as_of'] ?? null) === $asOf && is_string($context['fingerprint'] ?? null)
            && preg_match('/^[a-f0-9]{64}$/D', $context['fingerprint']) === 1;
    }
}
