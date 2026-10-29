<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\Performance\PlayerAbilitySnapshotStore;
use App\Services\Performance\PlayerSharedAbilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CapePlayerRatingController extends Controller
{
    public function __invoke(Request $request, string $player): JsonResponse
    {
        if (app()->environment('production') && config('integrations.jta.require_https', true) && !$request->secure()) {
            abort(426, 'HTTPS is required.');
        }
        $token = $request->user()?->currentAccessToken();
        abort_unless($request->bearerToken() && $token instanceof PersonalAccessToken
            && in_array('cape-ratings:read', $token->abilities ?? [], true)
            && $request->user()->hasRole('super-user'), 403);

        $record = Player::query()->findOrFail($player);
        $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
        $ratings = [];
        $unified = array_key_exists('all_age', $snapshot);
        $shared = null;
        if (empty($snapshot['reason'])) {
            if ($unified) {
                $shared = app(PlayerSharedAbilityService::class)->allAgeBadgeSnapshot()[$record->id] ?? null;
            }
            $estimates = $unified ? ($shared ? [$shared] : []) : (app(PlayerSharedAbilityService::class)->badgeSnapshot()[$record->id] ?? []);
            foreach ($estimates as $rating) {
                $score = $rating['score'] ?? null;
                if (!is_numeric($score) || !is_finite((float) $score) || $score < 0 || $score > 100) {
                    continue;
                }
                $ratings[] = [
                    'score' => (float) $score,
                    'cohort' => (string) $rating['cohort'],
                    'component' => (string) $rating['component'],
                    'confidence_label' => (string) ($rating['confidence_label'] ?? 'Limited'),
                    'baseline_status' => (string) ($rating['baseline_status'] ?? 'No connected main-trial baseline'),
                ] + ($unified ? ['rating_scope' => 'all_age', 'rating_status' => $rating['rating_status'],
                    'comparable' => (bool) $rating['comparable']] : [])
                    + (isset($rating['connection_status']) ? ['connection_status' => $rating['connection_status'],
                        'historical_connection' => (bool) ($rating['historical_connection'] ?? false),
                        'connection_age_days' => $rating['connection_age_days'] ?? null,
                        'connection_reference_date' => $rating['connection_reference_date'] ?? null] : []);
            }
        }
        $builtAt = $snapshot['built_at'] ?? null;
        return response()->json(['data' => [
            'source' => 'cape_tennis',
            'cape_tennis_player_id' => (int) $record->id,
            'model_version' => (string) PlayerSharedAbilityService::VERSION,
            'calculated_at' => $builtAt && $builtAt !== 'Not yet updated'
                ? CarbonImmutable::parse($builtAt, 'Africa/Johannesburg')->toIso8601String() : null,
            'stale' => (bool) ($snapshot['snapshot_stale'] ?? true),
            'ratings' => $ratings,
        ] + ($unified ? ['rating_scope' => 'all_age', 'rating_status' => $shared['rating_status'] ?? 'unrated',
            'rating_reason' => $shared['reason'] ?? $snapshot['reason'] ?? null] : [])])->header('Cache-Control', 'private, no-store');
    }
}

