<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\JtaPlayerResultsRequest;
use App\Http\Requests\Api\V1\LookupJtaPlayersRequest;
use App\Http\Requests\Api\V1\ResolveJtaPlayerRequest;
use App\Http\Resources\Api\V1\JtaPlayerResultResource;
use App\Http\Resources\Api\V1\JtaPlayerEventResultResource;
use App\Http\Resources\Api\V1\JtaPlayerSeriesRankingResource;
use App\Models\Player;
use App\Services\Integrations\JtaEventResultExportService;
use App\Services\Integrations\JtaResultExportService;
use App\Services\Integrations\JtaSeriesRankingExportService;
use App\Services\PlayerIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JtaIntegrationController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'api_version' => 'v1',
            'status' => 'ok',
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function resolvePlayer(
        ResolveJtaPlayerRequest $request,
        PlayerIdentityService $identityService,
    ): JsonResponse {
        $validated = $request->validated();
        $player = $identityService->find(
            $validated['first_name'],
            $validated['last_name'],
            $validated['date_of_birth'],
        );

        if (! $player) {
            return response()->json(['message' => 'No exact Cape Tennis player match was found.'], 404);
        }

        if ($identityService->findCandidates(
            $validated['first_name'],
            $validated['last_name'],
            $validated['date_of_birth'],
        )->count() > 1) {
            return response()->json(['message' => 'Multiple historical Cape Tennis player records match this identity.'], 409);
        }

        $request->attributes->set('jta_linked_player_id', (int) $player->id);

        return response()->json([
            'data' => [
                'cape_tennis_player_id' => (int) $player->id,
                'display_name' => trim((string) $player->name.' '.(string) $player->surname),
                'identity_match' => 'exact',
            ],
        ]);
    }

    public function lookupPlayers(
        LookupJtaPlayersRequest $request,
        PlayerIdentityService $identityService,
    ): JsonResponse {
        $validated = $request->validated();
        $exactCandidates = filled($validated['date_of_birth'] ?? null)
            ? $identityService->findCandidates(
                $validated['first_name'],
                $validated['last_name'],
                $validated['date_of_birth'],
            )
            : collect();
        $exact = $exactCandidates->count() === 1 ? $exactCandidates->first() : null;
        $possibleMatches = $identityService->findNameCandidates(
            $validated['first_name'],
            $validated['last_name'],
        );

        if ($exact) {
            $request->attributes->set('jta_linked_player_id', (int) $exact->id);
        }

        return response()->json([
            'data' => [
                'exact_match' => $exact ? [
                    'cape_tennis_player_id' => (int) $exact->id,
                    'display_name' => trim((string) $exact->name.' '.(string) $exact->surname),
                    'identity_match' => 'exact',
                ] : null,
                'possible_matches' => $possibleMatches->map(fn (Player $player) => [
                    'cape_tennis_player_id' => (int) $player->id,
                    'first_name' => (string) $player->name,
                    'last_name' => (string) $player->surname,
                    'date_of_birth' => $player->dateOfBirth
                        ? substr((string) $player->dateOfBirth, 0, 10)
                        : null,
                    'match_type' => $exact && (int) $exact->id === (int) $player->id ? 'exact' : 'name_only',
                ])->values(),
            ],
        ]);
    }

    public function playerResults(
        JtaPlayerResultsRequest $request,
        Player $player,
        JtaResultExportService $exportService,
    ): AnonymousResourceCollection {
        $request->attributes->set('jta_linked_player_id', (int) $player->id);
        $paginator = $exportService->paginate(
            $player,
            $request->updatedSince(),
            (int) $request->input('per_page', 50),
            $request->has('cursor'),
        );

        return JtaPlayerResultResource::collection($paginator)->additional([
            'meta' => [
                'api_version' => 'v1',
                'snapshot' => $request->boolean('full_snapshot') || ! $request->filled('updated_since'),
                'generated_at' => now()->toIso8601String(),
                'deletion_policy' => 'Missing results must be flagged for review, not hard-deleted.',
            ],
        ]);
    }

    public function playerEventResults(
        JtaPlayerResultsRequest $request,
        Player $player,
        JtaEventResultExportService $exportService,
    ): AnonymousResourceCollection {
        $request->attributes->set('jta_linked_player_id', (int) $player->id);
        $paginator = $exportService->paginate(
            $player,
            $request->updatedSince(),
            (int) $request->input('per_page', 50),
            $request->has('cursor'),
        );

        return JtaPlayerEventResultResource::collection($paginator)->additional([
            'meta' => [
                'api_version' => 'v1',
                'result_type' => 'placement',
                'snapshot' => $request->boolean('full_snapshot') || ! $request->filled('updated_since'),
                'generated_at' => now()->toIso8601String(),
                'deletion_policy' => 'Missing results must be flagged for review, not hard-deleted.',
            ],
        ]);
    }

    public function playerSeriesRankings(
        JtaPlayerResultsRequest $request,
        Player $player,
        JtaSeriesRankingExportService $exportService,
    ): AnonymousResourceCollection {
        $request->attributes->set('jta_linked_player_id', (int) $player->id);
        $paginator = $exportService->paginate(
            $player,
            $request->updatedSince(),
            (int) $request->input('per_page', 50),
            $request->has('cursor'),
        );

        return JtaPlayerSeriesRankingResource::collection($paginator)->additional([
            'meta' => [
                'api_version' => 'v1',
                'result_type' => 'series_ranking',
                'snapshot' => $request->boolean('full_snapshot') || ! $request->filled('updated_since'),
                'generated_at' => now()->toIso8601String(),
                'publication_policy' => 'Only the current official Cape Tennis published ranking lifecycle is exported.',
                'deletion_policy' => 'Missing rankings must be flagged for review, not hard-deleted.',
            ],
        ]);
    }
}
