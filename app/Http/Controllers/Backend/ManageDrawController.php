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
use Illuminate\Http\Request;
use App\Services\FeedInDrawService;

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
    $this->authorize('update', $draw);
    $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'draw_type' => 'nullable|integer|exists:draw_types,id',
            'draw_format_id' => 'nullable|exists:draw_formats,id',
            'draw_type_id' => 'nullable|exists:draw_types,id',
            'boxes' => 'nullable|integer|min:1|max:26',
            'playoff_size' => 'nullable|integer',
            'num_sets' => 'nullable|integer',
            'move_to_group_id' => 'nullable|integer',
        ]);

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
            'num_sets' => $data['num_sets'] ?? null,
        ], fn($v) => !is_null($v) && $v !== '');

        if (empty($drawData) && empty($updateData)) {
            return response()->json([ 'success' => false, 'message' => 'No settings provided' ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($draw, $drawData, $updateData, $data) {
            $draw->refresh();
            $lockedDraw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            abort_if($lockedDraw->locked || $lockedDraw->published, 403);
            if (isset($updateData['boxes'])) {
                $this->resizeGroups($draw, (int) $updateData['boxes'], $data['move_to_group_id'] ?? null);
            }
            if ($drawData) $draw->update($drawData);
            $draw->settings()->updateOrCreate(['draw_id' => $draw->id], $updateData);
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
    $this->authorize('update', $draw);
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

    // Get or create settings
    $updateData = [
      'playoff_config' => $validated['playoff_config'],
      'preset_key' => $validated['preset_key'] ?? null, // Store preset key
    ];
    
    if (!$draw->settings) {
      $draw->settings()->create(array_merge([
        'draw_id' => $draw->id,
      ], $updateData));
    } else {
      $draw->settings()->update($updateData);
    }

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
    $this->authorize('editNotes', $draw);
    $validated = $request->validate([
      'notes' => 'required|array',
      'notes.*' => 'nullable|string|max:5000',
    ]);

    if (!$draw->settings) {
      $draw->settings()->create([
        'draw_id' => $draw->id,
        'notes' => $validated['notes'],
      ]);
    } else {
      $draw->settings()->update([
        'notes' => $validated['notes'],
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => 'Notes saved successfully.',
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
