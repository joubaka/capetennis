<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\CategoryEvent;
use App\Models\DrawFormats;
use App\Models\DrawSetting;
use App\Models\Player;
use App\Models\Registration;
use App\Domain\Draws\Services\TennisScoreFormat;
use Illuminate\Http\Request;
use App\Services\FeedInDrawService;
use Illuminate\Validation\Rule;

class ManageDrawController extends Controller
{
  public function index($id)
  {
    $draw = Draw::with([
      'settings',
      'players',
      'groups.registrations.players',
      'categoryEvent.category',
      'registrations.players'
    ])->findOrFail($id);

    $drawFormats = DrawFormats::all();

    // Get all draws within the same category_event (for drag/drop preview)
    $allDraws = Draw::with(['registrations.players', 'drawFormat'])
      ->where('category_event_id', $draw->category_event_id)
      ->orderBy('drawName')
      ->get();

    // Get eligible registrations NOT assigned to any draw in this category_event
    $eligibleRegistrations = Registration::whereHas('categoryEvents', function ($query) use ($draw) {
      $query->where('category_event_id', $draw->category_event_id);
    })->with(['players:id,name,surname'])
      ->get()
      ->filter(function ($registration) use ($allDraws) {
        // Exclude if already in any draw
        foreach ($allDraws as $d) {
          if ($d->registrations->contains($registration)) {
            return false;
          }
        }
        return true;
      });

    return view('backend.draw.manage', compact(
      'draw',
      'drawFormats',
      'eligibleRegistrations',
      'allDraws'
    ));
  }


  public function updateSettings(Request $request, Draw $draw)
  {
    $structuralFields = ['name', 'draw_type', 'draw_format_id', 'draw_type_id', 'boxes', 'playoff_size', 'move_to_group_id'];
    $competitionRulesOnly = ! $request->hasAny($structuralFields);
    $this->authorize($competitionRulesOnly ? 'editCompetitionRules' : 'update', $draw);
    $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'draw_type' => 'nullable|integer|exists:draw_types,id',
            'draw_format_id' => 'nullable|exists:draw_formats,id',
            'draw_type_id' => 'nullable|exists:draw_types,id',
            'boxes' => 'nullable|integer|min:1|max:26',
            'playoff_size' => 'nullable|integer',
            'num_sets' => ['nullable', 'integer', Rule::in([1, 2, 3, 5])],
            'score_format' => ['nullable', 'string', Rule::in(TennisScoreFormat::keys())],
            'match_duration_minutes' => 'sometimes|integer|min:1|max:240',
            'changeover_minutes' => 'sometimes|integer|min:0|max:60',
            'move_to_group_id' => 'nullable|integer',
            'playoff_config' => 'sometimes|required|array|min:1',
            'playoff_config.*.name' => 'required|string|max:255',
            'playoff_config.*.slug' => 'required|string|max:255|distinct',
            'playoff_config.*.size' => ['required', 'integer', Rule::in([2, 4, 8, 16, 32])],
            'playoff_config.*.positions' => 'present|array',
            'playoff_config.*.positions.*' => 'integer|min:1',
            'playoff_config.*.enabled' => 'required|boolean',
            'playoff_config.*.group_order' => 'sometimes|array',
            'playoff_config.*.group_order.*' => 'string|regex:/^[A-Z]$/',
            'preset_key' => 'nullable|string|max:255',
        ]);

        $effectiveFormat = $data['score_format'] ?? $draw->settings?->score_format;
        if ($effectiveFormat === TennisScoreFormat::TIMED) {
            abort_if($draw->isTeamDraw(), 422, 'Timed scoring is available for individual draws.');
            foreach (['match_duration_minutes', 'changeover_minutes'] as $field) {
                if (! array_key_exists($field, $data) && $draw->settings?->{$field} === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Set the match duration and changeover time for timed matches.']);
                }
            }
        }

        // The manage form uses draw-level names, while the engine API uses the
        // settings column names. Normalize both inputs before persisting.
        $drawData = array_filter([
            'drawName' => $data['name'] ?? null,
            'drawType_id' => $data['draw_type'] ?? null,
        ], fn($v) => !is_null($v) && $v !== '');
        $updateData = array_filter([
            'draw_format_id' => $data['draw_format_id'] ?? null,
            'draw_type_id' => $data['draw_type_id'] ?? ($data['draw_type'] ?? null),
            'boxes' => $data['boxes'] ?? null,
            'playoff_size' => $data['playoff_size'] ?? null,
            'num_sets' => isset($data['score_format'])
                ? TennisScoreFormat::maxSets($data['score_format'])
                : ($data['num_sets'] ?? null),
            'score_format' => $data['score_format'] ?? null,
            'match_duration_minutes' => $data['match_duration_minutes'] ?? null,
            'changeover_minutes' => $data['changeover_minutes'] ?? null,
        ], fn($v) => !is_null($v) && $v !== '');

        if (array_key_exists('playoff_config', $data)) {
            abort_if($draw->isRoundRobinOnly(), 422, 'Playoff settings are not available for a round-robin-only draw.');
            foreach ($data['playoff_config'] as $index => $playoff) {
                if ($playoff['enabled'] && empty($playoff['positions'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "playoff_config.$index.positions" => 'Choose at least one group position for each enabled playoff.',
                    ]);
                }
            }
            $updateData['playoff_config'] = $data['playoff_config'];
            $updateData['preset_key'] = $data['preset_key'] ?? null;
        }

        if (empty($drawData) && empty($updateData)) {
            return response()->json([ 'success' => false, 'message' => 'No settings provided' ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($draw, $drawData, $updateData, $data, $competitionRulesOnly) {
            $draw->refresh();
            $lockedDraw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            abort_if($lockedDraw->locked, 403, 'Unlock the draw before changing its settings.');
            abort_if(
                $lockedDraw->event?->hasRecordedResults(),
                403,
                'Match format and rules cannot be changed after the first tournament result has been recorded.'
            );
            abort_if(
                ! $competitionRulesOnly && $lockedDraw->published,
                403,
                'Structural draw settings cannot be changed while the draw is published.'
            );
            abort_if(
                array_key_exists('playoff_config', $updateData) && $lockedDraw->isRoundRobinOnly(),
                422,
                'Playoff settings are not available for a round-robin-only draw.'
            );
            if (isset($updateData['boxes'])) {
                $this->resizeGroups($draw, (int) $updateData['boxes'], $data['move_to_group_id'] ?? null);
            }
            if ($drawData) $draw->update($drawData);
            $replacePlayoffs = array_key_exists('playoff_config', $updateData)
                && app(\App\Services\Draw\AutomaticPlayoffPreparationService::class)->replaceUnbookedPlaceholders($draw, $updateData['playoff_config']);
            $draw->settings()->updateOrCreate(['draw_id' => $draw->id], $updateData);
            if (array_key_exists('playoff_config', $updateData)) {
                app(\App\Services\Draw\AutomaticPlayoffPreparationService::class)->prepare($draw, $replacePlayoffs);
            }
        });

        // Refresh the draw to get updated settings
        $draw->refresh();
        $settings = $draw->settings;

        // For AJAX requests, return JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully.',
                'settings' => $settings,
                'groups_count' => $draw->groups()->count(),
            ]);
        }

        // For normal form submit, redirect back
        return back()->with('success', 'Draw settings updated.');
  }

  /**
   * Recreate groups when boxes count changes.
   * Preserves all players by moving them to Group A.
   */
  public function recreateGroups(Draw $draw, int $numGroups)
  {
    return $this->resizeGroups($draw, $numGroups);
  }

  private function resizeGroups(Draw $draw, int $count, ?int $moveTo = null)
  {
    abort_if($draw->isTwoStageRoundRobin() && $draw->groups()->where('round_robin_phase', 2)->exists(), 409,
      'Group membership is frozen after progression to the second round robin.');
    $groups = $draw->groups()->orderBy('name')->get();
    if ($groups->count() === $count) return $groups;
    if ($draw->drawFixtures()->whereHas('fixtureResults')->exists()) {
      throw \Illuminate\Validation\ValidationException::withMessages(['boxes' => 'Groups cannot change after scores have been recorded.']);
    }
    $removed = $groups->slice($count);
    if ($removed->isNotEmpty()) {
      if ($draw->drawFixtures()->whereIn('draw_group_id', $removed->pluck('id'))->exists()) {
        throw \Illuminate\Validation\ValidationException::withMessages(['boxes' => 'These groups have fixtures. Keep the groups until the existing draw is formally reset.']);
      }
      $players = \App\Models\DrawGroupRegistration::whereIn('draw_group_id', $removed->pluck('id'))->orderBy('seed')->get();
      $target = $groups->take($count)->firstWhere('id', $moveTo);
      if ($players->isNotEmpty() && !$target) {
        throw \Illuminate\Validation\ValidationException::withMessages(['move_to_group_id' => 'Choose a remaining group for players from the removed groups.']);
      }
      if ($target) {
        $seed = (int) $target->groupRegistrations()->max('seed');
        foreach ($players as $player) {
          $target->registrations()->syncWithoutDetaching([$player->registration_id => ['seed' => ++$seed]]);
        }
      }
      \App\Models\DrawGroupRegistration::whereIn('draw_group_id', $removed->pluck('id'))->delete();
      $draw->groups()->whereIn('id', $removed->pluck('id'))->delete();
    }
    for ($index = $groups->count(); $index < $count; $index++) {
      $draw->groups()->firstOrCreate(['name' => chr(65 + $index)]);
    }
    DrawAuditLog::record($draw->id, 'groups_resized', null, ['previous_count' => $groups->count(), 'count' => $count]);
    return $draw->groups()->orderBy('name')->get();
  }


  /**
   * Update playoff configuration (bracket sizes and positions)
   */
  public function updatePlayoffConfig(Request $request, Draw $draw)
  {
    $this->authorize('editCompetitionRules', $draw);
    abort_if($draw->isRoundRobinOnly(), 422, 'Playoff settings are not available for a round-robin-only draw.');
    $validated = $request->validate([
      'playoff_config' => 'required|array|min:1', // At least one playoff
      'playoff_config.*.name' => 'required|string',
      'playoff_config.*.slug' => 'required|string',
      'playoff_config.*.size' => 'required|integer|min:2',
      'playoff_config.*.positions' => 'required|array|min:1', // At least 1 position per playoff
      'playoff_config.*.enabled' => 'required|boolean',
      'preset_key' => 'nullable|string', // Accept preset key
    ]);

    \Log::info("🔧 [updatePlayoffConfig] Received playoff config", [
      'draw_id' => $draw->id,
      'config' => $validated['playoff_config'],
      'preset_key' => $validated['preset_key'] ?? null,
    ]);

    \Illuminate\Support\Facades\DB::transaction(function () use ($draw, $validated): void {
      $lockedDraw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
      abort_if(
        $lockedDraw->locked || $lockedDraw->event?->hasRecordedResults(),
        403,
        'Playoff rules cannot be changed after the first result has been recorded.'
      );

      $replacePlayoffs = app(\App\Services\Draw\AutomaticPlayoffPreparationService::class)->replaceUnbookedPlaceholders($lockedDraw, $validated['playoff_config']);
      $lockedDraw->settings()->updateOrCreate(
        ['draw_id' => $lockedDraw->id],
        [
          'playoff_config' => $validated['playoff_config'],
          'preset_key' => $validated['preset_key'] ?? null,
        ]
      );
      app(\App\Services\Draw\AutomaticPlayoffPreparationService::class)->prepare($lockedDraw, $replacePlayoffs);
    });

    \Log::info("✅ [updatePlayoffConfig] Playoff config saved successfully");

    return response()->json([
      'success' => true,
      'message' => 'Playoff configuration saved successfully.',
      'playoff_config' => $validated['playoff_config'],
      'preset_key' => $validated['preset_key'] ?? null,
    ]);
  }

  /**
   * Update draw notes/rules
   */
  public function updateNotes(Request $request, Draw $draw)
  {
    $this->authorize('editCompetitionRules', $draw);
    $validated = $request->validate([
      'notes' => 'required|array',
      'notes.*' => 'nullable|string|max:5000',
      'notes_print' => 'sometimes|array',
      'notes_print.*' => 'boolean',
      'schedule_visibility' => 'sometimes|required|in:' . DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH . ',' . DrawSetting::SCHEDULE_VISIBILITY_FULL,
    ]);

    \Illuminate\Support\Facades\DB::transaction(function () use ($draw, $validated): void {
      $lockedDraw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
      abort_if(
        $lockedDraw->locked || $lockedDraw->event?->hasRecordedResults(),
        403,
        'Rules cannot be changed after the first result has been recorded.'
      );

      $lockedDraw->settings()->updateOrCreate(
        ['draw_id' => $lockedDraw->id],
        [
          'notes' => array_replace($lockedDraw->settings?->notes ?? [], $validated['notes']),
          'notes_print' => array_replace($lockedDraw->settings?->notes_print ?? [], $validated['notes_print'] ?? []),
          'schedule_visibility' => $validated['schedule_visibility']
            ?? $lockedDraw->settings?->schedule_visibility
            ?? DrawSetting::SCHEDULE_VISIBILITY_FULL,
        ]
      );
    });

    return response()->json([
      'success' => true,
      'message' => 'Notes saved successfully.',
      'printable_rules' => app(\App\Domain\Draws\Services\DrawRulesService::class)->printable($draw->fresh()),
    ]);
  }

  /**
   * Generate playoff brackets from Round Robin standings
   */
  public function generatePlayoffBrackets(Request $request, Draw $draw)
  {
    $this->authorize('generateBrackets', $draw);

    try {
      // Delegate to RoundRobinController which has the generation logic
      return app(RoundRobinController::class)->generateMainBracket($request, $draw);
    } catch (\Exception $e) {
      \Log::error('[generatePlayoffBrackets] Error', [
        'draw_id' => $draw->id,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to generate playoff brackets: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Get playoff brackets data for rendering (read-only via BracketRenderService)
   */
  public function getPlayoffBrackets(Request $request, Draw $draw)
  {
    $this->authorize('view', $draw);
    try {
      $stages = $request->input('stages', \App\Domain\Draws\Services\BracketRenderService::ALL_STAGES);
      $data = app(\App\Domain\Draws\Services\BracketRenderService::class)
        ->buildBracketData($draw, $stages);

      return response()->json([
        'success' => true,
        'data'    => $data,
      ]);
    } catch (\Exception $e) {
      \Log::error('[getPlayoffBrackets] Error', [
        'draw_id' => $draw->id,
        'error'   => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to load playoff brackets: ' . $e->getMessage(),
      ], 500);
    }
  }


  // Add more functions as needed for tabs like:
  // assignPlayers(Request $request, Draw $draw)
  // createGroups(Request $request, Draw $draw)



  public function showFeedInDraw()
  {
      $svg = (new FeedInDrawService())->testMatchBox(); // Can be 16 or 32
      return view('draw.feedin', compact('svg'));
  }

}
