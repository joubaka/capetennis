<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, DB, Log};

/** Ordered offline materialization, with a rotating resumable active-Plus audience. */
class PlayerPlusDerivedRefresh
{
    public const LOCK = 'player-plus:refresh-derived';

    public function __construct(private PlayerPlusDerivedRefreshState $stateStore, private PlayerAbilitySnapshotStore $snapshots,
        private PlayerRatingHistoryStore $history, private PlayerSharedAbilityService $ability, private PlayerPlusEntitlement $entitlement, private PlayerPlusDerivedCommandRunner $commands) {}

    public function run(bool $resume = false, bool $scheduled = false, int $limit = 10, int $maxSeconds = 240, ?string $reference = null): array
    {
        if ($limit < 1 || $limit > 100 || $maxSeconds < 1 || $maxSeconds > 240) throw new \InvalidArgumentException('Choose a player limit from 1–100 and a time budget from 1–240 seconds.');
        $now = CarbonImmutable::now('Africa/Johannesburg');
        if ($scheduled && ($now->format('H:i') < ($resume ? '03:07' : '03:02') || $now->format('H:i') > '06:57')) return $this->report(null, 'outside_window');
        $lock = Cache::store('file')->lock(self::LOCK, 28800);
        if (!$lock->get()) return $this->report($this->stateStore->read(), 'busy');
        $state = null;
        try {
            $old = $this->stateStore->read();
            if ($resume) {
                if (!$old) return $this->report(null, 'not_resumable');
                $retryable = $old['status'] === 'failed' && (($old['reason'] === 'ability_failed' && $old['ability_attempts'] < 3)
                    || ($old['reason'] === 'history_failed' && $old['history_attempts'] < 3));
                if ($old['status'] !== 'pending' && !$retryable) return $this->report($old, in_array($old['status'], ['failed', 'context_changed'], true) ? null : 'not_resumable');
                $state = $old;
                if ($state['cycle_date'] !== $now->toDateString()) return $this->stop($state, 'context_changed', 'source_context_changed');
                if ($retryable) { $state['status'] = 'pending'; $state['reason'] = null; $this->save($state); }
            } else {
                // Start the new context at the last completed cursor, not zero: partial nights cannot starve later IDs.
                $cursor = $old['cursor'] ?? 0;
                $state = ['version' => PlayerPlusDerivedRefreshState::VERSION, 'cycle_date' => $now->toDateString(), 'status' => 'pending', 'stage' => 'ability',
                    'context' => null, 'cursor' => $cursor, 'start_cursor' => $cursor, 'wrapped' => false, 'player_id' => null,
                    'processed' => 0, 'progress_failures' => 0, 'event_failures' => 0, 'ability_attempts' => 0, 'history_attempts' => 0,
                    'reference' => PlayerRatingRefreshDiagnostics::reference($reference), 'reason' => null,
                    'started_at' => $now->toIso8601String(), 'updated_at' => $now->toIso8601String()];
                $this->save($state);
            }
            $started = $this->clock(); $attempted = 0;
            while (true) {
                if (!$this->withinBudget($started, $maxSeconds, $scheduled)) return $this->report($state);
                if ($state['stage'] === 'ability') {
                    if (!$this->withinBudget($started, $maxSeconds, $scheduled)) return $this->report($state);
                    $state['ability_attempts']++; $this->save($state);
                    if ($this->commands->run('player-ability:refresh', ['--reference' => $state['reference']]) !== 0) return $this->stop($state, 'failed', 'ability_failed');
                    $state['context'] = $this->context(false); // Explicit fresh reader after the canonical writer.
                    $state['stage'] = 'history'; $this->save($state); continue;
                }
                if (!$this->contextMatches($state['context'], $state['stage'] !== 'history')) return $this->stop($state, 'context_changed', 'source_context_changed');
                if ($state['stage'] === 'history') {
                    if (!$this->withinBudget($started, $maxSeconds, $scheduled)) return $this->report($state);
                    $state['history_attempts']++; $this->save($state);
                    if ($this->commands->run('player-rating-history:refresh') !== 0) return $this->stop($state, 'failed', 'history_failed');
                    if (!$this->contextMatches($state['context'], true)) return $this->stop($state, 'context_changed', 'source_context_changed');
                    $state['stage'] = 'progress'; $this->save($state); continue;
                }
                if ($state['player_id'] === null) {
                    if ($attempted >= $limit) return $this->report($state);
                    $query = $this->entitlement->activePlayers()->where('players.id', '>', $state['cursor']);
                    if ($state['wrapped']) $query->where('players.id', '<=', $state['start_cursor']);
                    $player = $query->orderBy('players.id')->first(['players.id']);
                    if (!$player) {
                        if (!$state['wrapped'] && $state['start_cursor'] > 0) { $state['wrapped'] = true; $state['cursor'] = 0; $this->save($state); continue; }
                        $state['status'] = $state['progress_failures'] || $state['event_failures'] ? 'complete_with_errors' : 'complete';
                        $state['stage'] = 'done'; $this->save($state); return $this->report($state);
                    }
                    $state['player_id'] = (int) $player->id; $state['stage'] = 'progress'; $this->save($state);
                }
                $id = $state['player_id'];
                if (!$this->entitlement->activePlayers()->whereKey($id)->exists()) {
                    $state['cursor'] = $id; $state['player_id'] = null; $state['stage'] = 'progress'; $attempted++; $this->save($state); continue;
                }
                if ($state['stage'] === 'progress') {
                    if (!$this->withinBudget($started, $maxSeconds, $scheduled)) return $this->report($state);
                    if ($this->playerCommand('player-match-progress:refresh', $id) !== 0) $state['progress_failures']++;
                    $state['stage'] = 'events'; $this->save($state); continue;
                }
                if (!$this->withinBudget($started, $maxSeconds, $scheduled)) return $this->report($state);
                if ($this->playerCommand('player-event-ratings:refresh', $id) !== 0) $state['event_failures']++;
                $state['cursor'] = $id; $state['player_id'] = null; $state['stage'] = 'progress'; $state['processed']++; $attempted++; $this->save($state);
            }
        } catch (\Throwable $error) {
            Log::warning('Player Plus derived refresh stopped; saved outputs retained.', ['exception_type' => get_class($error)]);
            $reason = match ($state['stage'] ?? null) { 'ability' => 'ability_failed', 'history' => 'history_failed', default => 'base_context_unavailable' };
            return $state ? $this->stop($state, 'failed', $reason) : ['status' => 'failed', 'partial' => true, 'complete' => false, 'exit_code' => 1];
        } finally { $lock->release(); }
    }

    private function context(bool $needHistory): array
    {
        $snapshot = $this->snapshots->freshCurrent(); $source = $this->ability->fingerprint();
        if (($snapshot['reason'] ?? null) !== null || ($snapshot['snapshot_as_of'] ?? null) !== CarbonImmutable::today('Africa/Johannesburg')->toDateString()
            || !hash_equals($source, (string) DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->value('source_fingerprint'))) throw new \RuntimeException('Current base context is unavailable.');
        if ($needHistory) {
            $history = $this->history->read();
            if (!$history || $history['as_of'] !== $snapshot['snapshot_as_of'] || !hash_equals($source, $history['source_fingerprint'])) throw new \RuntimeException('Matching saved history is unavailable.');
        }
        return ['as_of' => $snapshot['snapshot_as_of'], 'snapshot_version' => $snapshot['snapshot_version'], 'source_fingerprint' => $source, 'policy_hash' => $this->snapshots->policyHash()];
    }

    private function contextMatches(array $expected, bool $needHistory): bool
    {
        try { return $expected === $this->context($needHistory); } catch (\Throwable) { return false; }
    }

    private function playerCommand(string $command, int $player): int
    {
        try { return $this->commands->run($command, ['player' => $player]); }
        catch (\Throwable $error) { Log::warning('Player Plus derived player stage unavailable.', ['exception_type' => get_class($error)]); return 1; }
    }

    private function withinBudget(float $started, int $maxSeconds, bool $scheduled): bool
    {
        return $this->clock() - $started < $maxSeconds && (!$scheduled || CarbonImmutable::now('Africa/Johannesburg')->format('H:i') <= '06:57');
    }

    private function save(array &$state): void { $state['updated_at'] = CarbonImmutable::now('Africa/Johannesburg')->toIso8601String(); $this->stateStore->write($state); }
    private function stop(array $state, string $status, string $reason): array { $state['status'] = $status; $state['reason'] = $reason; $this->save($state); return $this->report($state); }
    protected function clock(): float { return microtime(true); }
    private function report(?array $state, ?string $override = null): array
    {
        $status = $override ?? $state['status'];
        return ['status' => $status, 'stage' => $state['stage'] ?? null, 'as_of' => $state['cycle_date'] ?? null,
            'processed_players' => $state['processed'] ?? 0, 'progress_failures' => $state['progress_failures'] ?? 0, 'event_failures' => $state['event_failures'] ?? 0,
            'ability_attempts' => $state['ability_attempts'] ?? 0, 'history_attempts' => $state['history_attempts'] ?? 0,
            'partial' => in_array($status, ['pending', 'failed', 'context_changed', 'busy'], true), 'complete' => $status === 'complete',
            'attempted_all' => in_array($status, ['complete', 'complete_with_errors'], true), 'reason' => $state['reason'] ?? null,
            'exit_code' => in_array($status, ['failed', 'context_changed', 'complete_with_errors'], true) ? 1 : 0];
    }
}
