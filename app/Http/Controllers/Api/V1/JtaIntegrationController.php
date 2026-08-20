<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkLookupJtaPlayersRequest;
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
use Illuminate\Support\Collection;

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
        $data = $this->identityLookup(
            $validated['first_name'],
            $validated['last_name'],
            $validated['date_of_birth'] ?? null,
            $identityService,
        );

        if ($data['exact_match']) {
            $request->attributes->set('jta_linked_player_id', (int) $data['exact_match']['cape_tennis_player_id']);
        }

        return response()->json(['data' => $data]);
    }

    public function bulkLookupPlayers(
        BulkLookupJtaPlayersRequest $request,
        PlayerIdentityService $identityService,
    ): JsonResponse {
        $validated = $request->validated();
        $candidates = $identityService->findNameCandidatesFor($validated['players']);
        $results = collect($validated['players'])->map(function (array $player) use ($identityService, $candidates): array {
            return ['client_reference' => (int) $player['client_reference']] + $this->identityLookup(
                $player['first_name'],
                $player['last_name'],
                $player['date_of_birth'] ?? null,
                $identityService,
                $candidates[(int) $player['client_reference']] ?? collect(),
            );
        })->values();

        return response()->json([
            'data' => [
                'request_id' => $validated['request_id'],
                'results' => $results,
            ],
            'meta' => [
                'api_version' => 'v1',
                'count' => $results->count(),
                'max_batch_size' => 50,
            ],
        ]);
    }

    private function identityLookup(
        string $firstName,
        string $lastName,
        ?string $dateOfBirth,
        PlayerIdentityService $identityService,
        ?Collection $knownCandidates = null,
    ): array {
        $allNameCandidates = $knownCandidates ?? $identityService->findNameCandidates(
            $firstName,
            $lastName,
            20,
        );
        $exactCandidates = filled($dateOfBirth)
            ? ($knownCandidates
                ? $allNameCandidates->filter(fn (Player $player) => substr((string) $player->dateOfBirth, 0, 10) === $dateOfBirth)->values()
                : $identityService->findCandidates(
                $firstName,
                $lastName,
                $dateOfBirth,
            ))
            : collect();
        $exact = $exactCandidates->count() === 1 ? $exactCandidates->first() : null;
        $possibleMatches = $allNameCandidates->take(20)->values();

        $status = match (true) {
            blank($dateOfBirth) => 'missing_date_of_birth',
            $exactCandidates->count() > 1 => 'ambiguous',
            (bool) $exact => 'exact',
            $possibleMatches->isNotEmpty() => 'name_only',
            default => 'not_found',
        };

        return [
            'status' => $status,
            'exact_match' => $exact ? [
                'cape_tennis_player_id' => (int) $exact->id,
                'display_name' => trim((string) $exact->name.' '.(string) $exact->surname),
                'identity_match' => 'exact',
            ] : null,
            'possible_matches' => $possibleMatches->map(fn (Player $player) => [
                'cape_tennis_player_id' => (int) $player->id,
                'first_name' => (string) $player->name,
                'last_name' => (string) $player->surname,
                'date_of_birth_status' => $this->dateOfBirthStatus($player, $dateOfBirth),
                'match_type' => $exact && (int) $exact->id === (int) $player->id ? 'exact' : 'name_only',
            ])->values()->all(),
        ];
    }

    private function dateOfBirthStatus(Player $player, ?string $requestedDate): string
    {
        $capeTennisDate = $player->dateOfBirth ? substr((string) $player->dateOfBirth, 0, 10) : null;

        return match (true) {
            blank($capeTennisDate) => 'missing',
            blank($requestedDate) => 'not_checked',
            hash_equals($capeTennisDate, $requestedDate) => 'match',
            default => 'different',
        };
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
