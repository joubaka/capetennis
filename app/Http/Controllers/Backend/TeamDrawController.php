<?php

namespace App\Http\Controllers\Backend;

use App\Domain\TeamDraw\RubberType;
use App\Domain\TeamDraw\TeamEventFormatDefinitionValidationException;
use App\Domain\TeamDraw\TeamEventFormatDefinitionValidator;
use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamTie;
use App\Services\FeatureFlags;
use App\Services\TeamDrawGenerationService;
use App\Services\TeamDrawRegenerationService;
use App\Services\TeamTieGenerationService;
use App\Services\TeamTieValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TeamDrawController  (v2)
 *
 * Handles team-event draw lifecycle: format management, tie generation,
 * rubber generation, and lifecycle-safe regeneration.
 *
 * All endpoints return JSON and are protected by the 'team_draw_v2'
 * feature flag where indicated.
 */
class TeamDrawController extends Controller
{
    public function __construct(
        private readonly TeamDrawGenerationService  $drawGenerator,
        private readonly TeamTieGenerationService   $tieGenerator,
        private readonly TeamDrawRegenerationService $regenerator,
        private readonly TeamTieValidationService   $validator,
        private readonly TeamEventFormatDefinitionValidator $formatDefinitionValidator,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Format Management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List formats available for an event (event-scoped + global defaults).
     *
     * GET /backend/team-draw/{event}/formats
     */
    public function listFormats(Event $event): JsonResponse
    {
        $formats = TeamEventFormat::with('rubbers')
            ->forEvent($event->id)
            ->orderByDesc('event_id')   // event-specific before global
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'formats' => $formats]);
    }

    /**
     * Create or update a format template for the event.
     *
     * POST /backend/team-draw/{event}/formats
     */
    public function storeFormat(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:191',
            'min_roster_size'    => 'required|integer|min:1|max:12',
            'max_roster_size'    => 'required|integer|min:1|max:12',
            'allow_player_reuse' => 'boolean',
            'is_default'         => 'boolean',
            'rubbers'            => 'required|array|min:1',
            'rubbers.*.sequence' => 'required|integer|min:1',
            'rubbers.*.rubber_code' => [
                'required',
                'string',
                'in:' . implode(',', RubberType::ALL),
            ],
            'rubbers.*.name'                  => 'required|string|max:100',
            'rubbers.*.gender_rule'           => 'nullable|string|in:male,female,mixed',
            'rubbers.*.player_count_per_team' => 'required|integer|min:1|max:4',
            'rubbers.*.singles_position'      => 'nullable|integer|min:1',
            'rubbers.*.reverse_from_position' => 'nullable|integer|min:1',
            'rubbers.*.is_required'           => 'boolean',
        ]);

        try {
            $this->formatDefinitionValidator->validate($validated);

            $format = DB::transaction(function () use ($validated, $event) {
                // If marking as default, unset any existing default for this event
                if (!empty($validated['is_default'])) {
                    TeamEventFormat::where('event_id', $event->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $format = TeamEventFormat::create([
                    'event_id'           => $event->id,
                    'name'               => $validated['name'],
                    'min_roster_size'    => $validated['min_roster_size'],
                    'max_roster_size'    => $validated['max_roster_size'],
                    'allow_player_reuse' => $validated['allow_player_reuse'] ?? false,
                    'is_default'         => $validated['is_default'] ?? false,
                ]);

                foreach ($validated['rubbers'] as $rubberData) {
                    TeamEventFormatRubber::create(array_merge(
                        $rubberData,
                        ['format_id' => $format->id]
                    ));
                }

                return $format->load('rubbers');
            });
        } catch (TeamEventFormatDefinitionValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            // Detect duplicate key violation (MySQL 1062 / SQLSTATE 23000) for rubber sequence
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rubber sequences must be unique within a format.',
                ], 422);
            }
            throw $e;
        }

        Log::info('[TeamDrawController] Format created', [
            'event_id'  => $event->id,
            'format_id' => $format->id,
            'name'      => $format->name,
        ]);

        return response()->json(['success' => true, 'format' => $format], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Draw ↔ Format attachment
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attach a format to an existing draw.
     *
     * POST /backend/team-draw/{draw}/attach-format
     */
    public function attachFormat(Request $request, Draw $draw): JsonResponse
    {
        $validated = $request->validate([
            'format_id' => 'required|integer|exists:team_event_formats,id',
        ]);

        $draw->team_event_format_id = $validated['format_id'];
        $draw->save();

        return response()->json([
            'success' => true,
            'message' => 'Format attached to draw.',
            'draw_id' => $draw->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Teams in Draw
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sync the explicit team list for a draw.
     *
     * POST /backend/team-draw/{draw}/sync-teams
     */
    public function syncTeams(Request $request, Draw $draw): JsonResponse
    {
        $validated = $request->validate([
            'team_ids'   => 'required|array|min:2',
            'team_ids.*' => 'integer|exists:teams,id',
        ]);

        $draw->teams_in_draw()->sync($validated['team_ids']);

        return response()->json([
            'success'  => true,
            'message'  => 'Teams updated for draw.',
            'team_ids' => $validated['team_ids'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tie Generation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate ties for a draw from its current team list.
     *
     * POST /backend/team-draw/{draw}/generate-ties
     */
    public function generateTies(Request $request, Draw $draw): JsonResponse
    {
        $validated = $request->validate([
            'team_ids'       => 'nullable|array|min:2',
            'team_ids.*'     => 'integer|exists:teams,id',
            'allow_override' => 'boolean',
        ]);

        $allowOverride = (bool) ($validated['allow_override'] ?? false);

        // Resolve teams: from request override or from draw's sync'd list
        if (!empty($validated['team_ids'])) {
            $teams = Team::whereIn('id', $validated['team_ids'])->get();
        } else {
            $teams = $draw->teams_in_draw;
        }

        if ($teams->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'At least 2 teams are required to generate ties.',
            ], 422);
        }

        try {
            $format = $draw->teamEventFormat;
            $ties   = $this->drawGenerator->generate($draw, $teams, $format, $allowOverride);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'   => true,
            'message'   => "{$ties->count()} tie(s) generated.",
            'ties_count' => $ties->count(),
            'ties'      => $ties->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rubber Generation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate rubbers for all ties in a draw using the attached format.
     *
     * POST /backend/team-draw/{draw}/generate-rubbers
     */
    public function generateRubbers(Request $request, Draw $draw): JsonResponse
    {
        $validated = $request->validate([
            'allow_override' => 'boolean',
        ]);

        $allowOverride = (bool) ($validated['allow_override'] ?? false);

        try {
            $rubbers = $this->tieGenerator->generateForAllTies($draw, $allowOverride);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'        => true,
            'message'        => "{$rubbers->count()} rubber(s) generated.",
            'rubbers_count'  => $rubbers->count(),
        ]);
    }

    /**
     * Generate rubbers for a single tie.
     *
     * POST /backend/team-draw/ties/{tie}/generate-rubbers
     */
    public function generateRubbersForTie(Request $request, TeamTie $tie): JsonResponse
    {
        $validated = $request->validate([
            'allow_override' => 'boolean',
        ]);

        $allowOverride = (bool) ($validated['allow_override'] ?? false);

        try {
            $rubbers = $this->tieGenerator->generateForTie($tie, $allowOverride);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'       => true,
            'message'       => "{$rubbers->count()} rubber(s) generated for tie #{$tie->id}.",
            'rubbers_count' => $rubbers->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Regeneration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Regenerate ties (and optionally rubbers) for a draw.
     *
     * POST /backend/team-draw/{draw}/regenerate
     */
    public function regenerate(Request $request, Draw $draw): JsonResponse
    {
        $validated = $request->validate([
            'team_ids'           => 'nullable|array|min:2',
            'team_ids.*'         => 'integer|exists:teams,id',
            'regenerate_rubbers' => 'boolean',
            'allow_override'     => 'boolean',
        ]);

        $allowOverride      = (bool) ($validated['allow_override'] ?? false);
        $regenerateRubbers  = (bool) ($validated['regenerate_rubbers'] ?? false);

        if (!empty($validated['team_ids'])) {
            $teams = Team::whereIn('id', $validated['team_ids'])->get();
        } else {
            $teams = $draw->teams_in_draw;
        }

        if ($teams->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'At least 2 teams are required to regenerate.',
            ], 422);
        }

        try {
            $result = $this->regenerator->regenerate(
                $draw,
                $teams,
                $draw->teamEventFormat,
                $regenerateRubbers,
                $allowOverride
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'       => true,
            'message'       => "Regenerated {$result['ties']->count()} tie(s)." .
                ($regenerateRubbers ? " {$result['rubbers']->count()} rubber(s) rebuilt." : ''),
            'ties_count'    => $result['ties']->count(),
            'rubbers_count' => $result['rubbers']->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tie Status / Publish
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate and transition a tie to 'validated' status.
     *
     * POST /backend/team-draw/ties/{tie}/validate
     */
    public function validateTie(TeamTie $tie): JsonResponse
    {
        try {
            $this->validator->assertTieComplete($tie);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $tie->status = TeamTie::STATUS_VALIDATED;
        $tie->save();

        return response()->json(['success' => true, 'message' => "Tie #{$tie->id} validated."]);
    }

    /**
     * Publish a tie (must be validated first).
     *
     * POST /backend/team-draw/ties/{tie}/publish
     */
    public function publishTie(TeamTie $tie): JsonResponse
    {
        if ($tie->status !== TeamTie::STATUS_VALIDATED) {
            return response()->json([
                'success' => false,
                'message' => "Tie #{$tie->id} must be validated before publishing (status: {$tie->status}).",
            ], 422);
        }

        $tie->status       = TeamTie::STATUS_PUBLISHED;
        $tie->published_at = now();
        $tie->save();

        return response()->json(['success' => true, 'message' => "Tie #{$tie->id} published."]);
    }
}
