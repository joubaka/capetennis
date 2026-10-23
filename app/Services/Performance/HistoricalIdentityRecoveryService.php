<?php

namespace App\Services\Performance;

use App\Models\{Event, HistoricalIdentityRecovery, TeamFixturePlayer, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

final class HistoricalIdentityRecoveryService
{
    private function require(bool $valid, string $message): void
    {
        if (!$valid) { throw ValidationException::withMessages(['recovery' => $message]); }
    }

    /** No writes, including on ordinary rating reads. Changed sources fail closed. */
    public function resolve(Event $event, TeamFixturePlayer $row, int $side): ?array
    {
        if (!Schema::hasTable('historical_identity_recoveries')) { return null; }
        $record = HistoricalIdentityRecovery::where('event_id', $event->id)->where('fixture_player_id', $row->id)->where('side', $side)->first();
        if (!$record) { return null; }
        return $this->resolveRecord($event, $record->getAttributes());
    }

    public function records(Event $event): array
    {
        if (!Schema::hasTable('historical_identity_recoveries')) { return []; }
        $records = DB::table('historical_identity_recoveries')->where('event_id', $event->id)->limit(501)->get();
        if ($records->count() > 500) { throw new \OverflowException('Historical recovery source limit exceeded.'); }
        return $records->mapWithKeys(fn ($record) => [$record->fixture_player_id.'-'.$record->side => (array) $record])->all();
    }

    public function resolveRecord(Event $event, array $record): ?array
    {
        try { return $this->validated($event, $record); }
        catch (ValidationException|\Illuminate\Database\Eloquent\ModelNotFoundException) { return null; }
    }

    /** Full raw dependencies of sourceFingerprint, captured once, compared in batches on saved reads. */
    public function context(array $recoveries): array
    {
        if (!$recoveries) { return []; }
        $context = [];
        $capture = function (string $table, string $column, array $ids, array $columns = ['*']) use (&$context): array {
            $rows = DB::table($table)->whereIn($column, array_unique($ids))->orderBy('id')->limit(50001)->get($columns)->map(fn ($row) => (array) $row)->all();
            if (count($rows) > 50000) { throw new \OverflowException('Recovery context exceeds bounded limits.'); }
            return $context[$table] = $rows;
        };
        $rows = $capture('team_fixture_players', 'id', array_column($recoveries, 'fixture_player_id'));
        $fixtures = $capture('team_fixtures', 'id', array_column($rows, 'team_fixture_id'));
        $draws = $capture('draws', 'id', array_column($fixtures, 'draw_id'));
        $teams = $capture('teams', 'id', array_column($recoveries, 'source_team_id'));
        $fields = $capture('category_events', 'id', [...array_column($draws, 'category_event_id'), ...array_column($teams, 'category_event_id')]);
        $capture('categories', 'id', array_column($fields, 'category_id'));
        $capture('team_ties', 'id', array_column($fixtures, 'team_tie_id'));
        $capture('team_fixture_results', 'team_fixture_id', array_column($fixtures, 'id'));
        $capture('team_players', 'team_id', array_column($teams, 'id'));
        $importTeams = DB::table('no_profile_team_players')->whereIn('id', [...array_column($rows, 'team1_no_profile_id'), ...array_column($rows, 'team2_no_profile_id')])->pluck('team_id')->all();
        $capture('no_profile_team_players', 'team_id', [...array_column($teams, 'id'), ...$importTeams]);
        $capture('team_regions', 'id', array_column($teams, 'region_id'));
        $capture('events', 'id', array_column($recoveries, 'event_id'));
        $capture('event_regions', 'event_id', array_column($recoveries, 'event_id'));
        $capture('players', 'id', array_column($recoveries, 'suggested_player_id'), ['id', 'name', 'surname', 'dateOfBirth', 'gender']);
        return $context;
    }

    private function validated(Event $event, array $entry): array
    {
        $review = app(HistoricalIdentityReviewService::class);
        $identity = $review->validateIdentity($event, (int) $entry['fixture_player_id'], (int) $entry['side'], (int) $entry['source_team_id'], (int) $entry['suggested_player_id']);
        $this->require(hash_equals($entry['source_fingerprint'], $review->sourceFingerprint($identity['row'], $identity['team']))
            && hash_equals($entry['target_fingerprint'], $review->targetFingerprint($identity['player']))
            && $entry['cohort'] === $identity['cohort'], 'Recovered evidence is stale; source or target changed.');
        return $identity;
    }

    public function import(Event $event, User $actor, array $manifest, bool $apply = false): array
    {
        abort_unless($actor->exists && User::find($actor->id)?->hasRole('super-user'), 403);
        $this->require((int) ($manifest['event_id'] ?? 0) === (int) $event->id, 'Manifest event does not match.');
        $entries = $manifest['entries'] ?? [];
        $fixtures = $manifest['fixture_ids'] ?? [];
        $this->require(is_array($entries) && count($entries) > 0 && count($entries) <= 500 && is_array($fixtures)
            && count($fixtures) === count(array_unique($fixtures)) && count($fixtures) === (int) ($manifest['expected_additional_matches'] ?? 0), 'Provide a bounded exact fixture scope and expected count.');
        $documents = $manifest['documents'] ?? [];
        $this->require(is_array($documents) && count($documents) > 0, 'Document hashes are required.');
        foreach ($documents as $document) {
            $this->require(is_string($document['reference'] ?? null) && strlen($document['reference']) >= 5
                && preg_match('/^[a-f0-9]{64}$/', $document['sha256'] ?? '') === 1, 'Each original document needs a reference and SHA256.');
        }
        $reviewed = CarbonImmutable::parse($manifest['reviewed_at'] ?? 'invalid');
        $this->require($reviewed->lessThanOrEqualTo(CarbonImmutable::now()), 'Review timestamp cannot be in the future.');
        $hash = hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR));
        return DB::transaction(function () use ($event, $actor, $manifest, $entries, $fixtures, $documents, $reviewed, $hash, $apply) {
            // Serialize imports for this event, including concurrent retries.
            if ($apply) { Event::whereKey($event->id)->lockForUpdate()->firstOrFail(); }
            $identities = []; $pending = []; $covered = [];
            foreach ($entries as $entry) {
                $this->require(is_array($entry) && (int) ($entry['event_id'] ?? 0) === (int) $event->id, 'Every side must belong to this event.');
                foreach (['fixture_player_id', 'fixture_id', 'side', 'source_team_id', 'suggested_player_id'] as $field) {
                    $this->require(is_int($entry[$field] ?? null) && $entry[$field] > 0, 'Exact integer match-side and identity identifiers are required.');
                }
                $this->require(is_string($entry['cohort'] ?? null), 'The reviewed cohort is required.');
                foreach (['evidence' => 30, 'reference' => 5, 'reason' => 15] as $field => $length) {
                    $this->require(is_string($entry[$field] ?? null) && strlen(trim($entry[$field])) >= $length, 'Substantive evidence, reference and reason are required.');
                }
                foreach (['source_fingerprint', 'target_fingerprint'] as $field) {
                    $this->require(preg_match('/^[a-f0-9]{64}$/', $entry[$field] ?? '') === 1, 'Exact source and target fingerprints are required.');
                }
                $identity = $this->validated($event, $entry);
                $row = $identity['row']; $side = (int) $entry['side'];
                $this->require(in_array((int) $row->team_fixture_id, $fixtures, true) && (int) ($entry['fixture_id'] ?? 0) === (int) $row->team_fixture_id, 'Side is outside the exact reviewed fixture scope.');
                $this->require(!isset($identities[$event->id][$row->id][$side]), 'Duplicate match side.');
                $identities[$event->id][$row->id][$side] = $identity;
                $covered[] = (int) $row->team_fixture_id;
                $existing = HistoricalIdentityRecovery::where('event_id', $event->id)->where('fixture_player_id', $row->id)->where('side', $side)->first();
                $this->require(!$existing || hash_equals($existing->manifest_hash, $hash), 'This side already has different immutable recovery evidence.');
                if (!$existing) {
                    $pending[] = array_intersect_key($entry, array_flip(['event_id', 'fixture_player_id', 'side', 'source_team_id', 'suggested_player_id', 'source_fingerprint', 'target_fingerprint', 'cohort', 'evidence', 'reference', 'reason']))
                        + ['created_by' => $actor->id, 'manifest_hash' => $hash, 'documents' => $documents, 'reviewed_at' => $reviewed, 'created_at' => now()];
                }
            }
            $expected = $fixtures; sort($expected); $covered = array_unique($covered); sort($covered);
            $this->require($expected === array_values($covered), 'Every reviewed fixture must have recovery evidence.');
            $this->require(count($pending) === 0 || count($pending) === count($entries), 'Recovery must be a complete atomic manifest or an exact retry.');
            if ($pending) {
                $baseline = collect(iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($event, null, CarbonImmutable::today('Africa/Johannesburg'))));
                $this->require($baseline->whereNull('reason')->whereIn('fixture_id', $fixtures)->isEmpty(), 'Additional fixtures must currently be excluded before this recovery.');
            }
            $matches = collect(iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($event, null, CarbonImmutable::today('Africa/Johannesburg'), new HistoricalIdentityPreviewContext($identities))));
            $eligible = $matches->whereNull('reason')->whereIn('fixture_id', $fixtures)->pluck('fixture_id')->sort()->values()->all();
            $this->require($eligible === $expected, 'Canonical publication, score or identity gates reject a reviewed fixture.');
            if ($apply) { foreach ($pending as $record) { HistoricalIdentityRecovery::create($record); } }
            return ['preview' => !$apply, 'created' => $apply ? count($pending) : 0, 'sides' => count($entries), 'fixtures' => count($fixtures), 'manifest_hash' => $hash];
        });
    }
}
