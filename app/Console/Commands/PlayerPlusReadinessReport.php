<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerPlusDerivedRefreshState, PlayerPlusEntitlement, PlayerPlusService, PlayerRatingAccess, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema};

/** Aggregate operator diagnostics only. Never changes access, billing or saved calculations. */
class PlayerPlusReadinessReport extends Command
{
    protected $signature = 'player-plus:readiness';

    protected $description = 'Read aggregate Player Plus configuration, saved-data and relationship readiness as JSON; performs no repairs';

    public function handle(): int
    {
        $report = [
            'read_only' => true,
            'checked_at' => now()->toIso8601String(),
            'schema' => $this->read(function () {
                $tables = [];
                foreach (['site_settings', 'users', 'players', 'user_players', 'player_plus_orders', 'player_plus_memberships', 'player_plus_subscriptions', 'player_plus_subscription_charges', 'player_plus_access_overrides', 'player_ability_snapshots', 'audit_events'] as $table) {
                    $tables[$table] = Schema::hasTable($table);
                }
                return ['available' => true, 'tables' => $tables];
            }),
            'offers' => $this->read(fn () => $this->offers()),
            'saved_ratings' => $this->read(fn () => $this->ratings()),
            'saved_history' => $this->read(fn () => $this->history()),
            'billing_review' => $this->read(fn () => $this->billing()),
            'relationships' => $this->read(fn () => $this->relationships()),
            'nightly_refresh' => $this->read(function () {
                $state = app(PlayerPlusDerivedRefreshState::class)->read();
                return $state ? ['available' => true] + array_intersect_key($state, array_flip(['status', 'stage', 'cycle_date', 'processed', 'progress_failures', 'event_failures', 'ability_attempts', 'history_attempts', 'updated_at', 'reason']))
                    : ['available' => false, 'reason' => 'No readable nightly refresh state is available.'];
            }),
            'limitations' => [
                'Aggregate diagnostics do not certify identity, relationship entitlement, rating accuracy or provider settlement.',
                'Missing relationship audit evidence is unknown, not proof that a relationship is invalid. No links or access are changed.',
                'No refresh, cancellation, reconciliation, notification or provider request is performed.',
            ],
        ];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function read(callable $reader): array
    {
        try {
            return $reader();
        } catch (\Throwable) {
            // Database/provider exception text can contain credentials or private values.
            return ['available' => false, 'reason' => 'Required schema or saved data could not be read.'];
        }
    }

    private function offers(): array
    {
        if (! Schema::hasTable('site_settings')) return ['available' => false, 'reason' => 'Settings schema is unavailable.'];
        // SiteSetting::get() populates a shared cache. Read only these non-secret settings directly instead.
        $values = DB::table('site_settings')->whereIn('key', [PlayerRatingAccess::SETTING, PlayerPlusService::MONTHLY_ENABLED_SETTING, PlayerPlusService::PRICE_SETTING, PlayerPlusService::MONTHLY_PRICE_SETTING])->pluck('value', 'key');
        $ready = PlayerPlusService::ready();
        $monthlyReady = PlayerPlusService::monthlyReady();
        $price = function (string $key, int $fallback) use ($values): array {
            $raw = $values->get($key);
            $valid = $raw !== null && ctype_digit((string) $raw) && (int) $raw >= PlayerPlusService::MIN_PRICE_CENTS && (int) $raw <= PlayerPlusService::MAX_PRICE_CENTS;

            return ['configured' => $raw !== null, 'configured_value_valid' => $valid, 'configured_cents' => $valid ? (int) $raw : null, 'effective_cents' => $valid ? (int) $raw : $fallback];
        };

        return ['available' => true, 'service_schema_ready' => $ready, 'monthly_schema_ready' => $monthlyReady,
            'sales_configured_enabled' => $values->get(PlayerRatingAccess::SETTING, '0') === '1',
            'sales_enabled' => $ready && $values->get(PlayerRatingAccess::SETTING, '0') === '1',
            'monthly_configured_enabled' => $values->get(PlayerPlusService::MONTHLY_ENABLED_SETTING, '0') === '1',
            'monthly_enabled' => $monthlyReady && $values->get(PlayerPlusService::MONTHLY_ENABLED_SETTING, '0') === '1',
            'recurring_provider_configured' => PlayerPlusService::monthlyProviderReady(),
            'annual_price' => $price(PlayerPlusService::PRICE_SETTING, PlayerPlusService::PRICE_CENTS),
            'monthly_price' => $price(PlayerPlusService::MONTHLY_PRICE_SETTING, 6900)];
    }

    private function ratings(): array
    {
        $store = app(PlayerAbilitySnapshotStore::class);
        $metadata = Schema::hasTable('player_ability_snapshots')
            ? DB::table('player_ability_snapshots')->where('snapshot_key', 'current')->first(['model_version', 'policy_hash']) : null;
        $saved = $store->current();
        $counts = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Unknown' => 0];
        $ratings = $saved['all_age']['ratings'] ?? [];
        foreach ($ratings as $rating) {
            $label = $rating['confidence_label'] ?? 'Unknown';
            $counts[array_key_exists($label, $counts) ? $label : 'Unknown']++;
        }

        return ['available' => ($saved['reason'] ?? null) === null, 'reason' => $saved['reason'] ?? null,
            'saved_record_present' => $metadata !== null,
            'policy_current' => $metadata ? (int) $metadata->model_version === PlayerSharedAbilityService::VERSION && hash_equals($store->policyHash(), (string) $metadata->policy_hash) : null,
            'as_of' => $saved['snapshot_as_of'] ?? null, 'stale' => $saved['snapshot_stale'] ?? null,
            'overall_rated_players' => count($ratings),
            'overall_comparable_players' => count(array_filter($ratings, fn ($rating) => ($rating['comparable'] ?? false) === true)),
            'overall_confidence_counts' => $counts];
    }

    private function history(): array
    {
        $saved = app(PlayerRatingHistoryStore::class)->read();
        if (! $saved) return ['available' => false, 'reason' => 'No readable saved history matching the current policy.'];
        $publicationCurrent = isset($saved['publication_manifest'])
            ? app(PlayerAbilitySnapshotStore::class)->published($saved['publication_manifest']) : null;
        $sourceCurrent = ($saved['source_fingerprint'] ?? null) === app(PlayerSharedAbilityService::class)->fingerprint();
        $stale = $saved['as_of'] < now('Africa/Johannesburg')->toDateString() || ! $sourceCurrent;
        $pairs = 0;
        $same = 0;
        $previous = [];
        // The canonical store bounds this to 12 months and at most 50,000 ratings per month.
        foreach ($saved['months'] as $month) {
            $ratings = $month['cohorts'][AllAgeRatingModel::COHORT] ?? [];
            foreach ($ratings as $id => $rating) {
                if (isset($previous[$id]['group'], $rating['group'])) {
                    $pairs++;
                    if ($previous[$id]['group'] === $rating['group']) $same++;
                }
            }
            $previous = $ratings;
        }

        return ['available' => $publicationCurrent !== false, 'as_of' => $saved['as_of'],
            'stale' => $stale, 'source_fingerprint_current' => $sourceCurrent,
            'publication_context_current' => $publicationCurrent, 'month_count' => count($saved['months']),
            'overall_adjacent_player_month_pairs' => $pairs, 'overall_same_group_pairs' => $same,
            'overall_same_group_ratio' => $pairs ? round($same / $pairs, 4) : null,
            'ratio_basis' => 'Consecutive calendar-month Overall CTR snapshots for the same player; not a rating-accuracy measure.'];
    }

    private function billing(): array
    {
        return ['available' => Schema::hasTable('player_plus_orders') || Schema::hasTable('player_plus_subscriptions'),
            'orders_needing_reconciliation' => Schema::hasTable('player_plus_orders') && Schema::hasColumn('player_plus_orders', 'reconciliation_reason')
                ? DB::table('player_plus_orders')->whereNotNull('reconciliation_reason')->where('reconciliation_reason', '!=', '')->count() : null,
            'pending_checkouts' => Schema::hasTable('player_plus_orders') ? DB::table('player_plus_orders')->where('status', 'pending')->count() : null,
            'subscription_cancellations_pending' => Schema::hasTable('player_plus_subscriptions')
                ? DB::table('player_plus_subscriptions')->whereNull('cancelled_at')->where(fn ($q) => $q->where('status', 'cancel_pending')->orWhereNotNull('cancellation_requested_at'))->count() : null];
    }

    private function relationships(): array
    {
        if (! Schema::hasTable('players') || ! Schema::hasTable('users')) return ['available' => false, 'reason' => 'Player/account schema is unavailable.'];
        $legacy = DB::table('players')->join('users', 'users.id', '=', 'players.userId')->selectRaw('players.id AS player_id, users.id AS user_id');
        $pivot = Schema::hasTable('user_players') ? DB::table('user_players')->join('players', 'players.id', '=', 'user_players.player_id')->join('users', 'users.id', '=', 'user_players.user_id')->selectRaw('players.id AS player_id, users.id AS user_id') : null;
        $pairs = clone $legacy;
        if ($pivot) $pairs->union($pivot);
        $active = app(PlayerPlusEntitlement::class)->activePlayers()->select('players.id');
        $activePairs = DB::query()->fromSub($pairs, 'relationships')->whereIn('relationships.player_id', $active);
        $total = (clone $activePairs)->count();
        $withAudit = null;
        if (Schema::hasTable('audit_events')) {
            $withAudit = (clone $activePairs)->whereExists(fn ($audit) => $audit->selectRaw('1')->from('audit_events')
                ->where('action', 'player.account-linked')->where('subject_type', Player::class)
                ->whereColumn('subject_id', 'relationships.player_id')->whereColumn('metadata->target_user_id', 'relationships.user_id'))->count();
        }

        return ['available' => true, 'legacy_owner_relationships' => (clone $legacy)->count(),
            'pivot_relationships' => $pivot ? (clone $pivot)->count() : null,
            'active_plus_players' => (clone $active)->count(), 'active_plus_relationship_pairs' => $total,
            'active_plus_pairs_with_link_audit' => $withAudit,
            'active_plus_pairs_with_unknown_link_audit' => $withAudit === null ? null : $total - $withAudit,
            'link_audit_status' => 'Unknown evidence is not an invalid relationship; no identity verification or access decision is inferred.'];
    }
}
