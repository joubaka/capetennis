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
        if (empty($snapshot['reason'])) {
            foreach (app(PlayerSharedAbilityService::class)->badgeSnapshot()[$record->id] ?? [] as $rating) {
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
                ];
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
        ]])->header('Cache-Control', 'private, no-store');
    }
}

