<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
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
    $data = $request->validate([
            'draw_format_id' => 'nullable|exists:draw_formats,id',
            'draw_type_id' => 'nullable|exists:draw_types,id',
            'boxes' => 'nullable|integer|min:1|max:26',
            'playoff_size' => 'nullable|integer',
            'num_sets' => 'nullable|integer',
        ]);

        // Require at least one setting to update
        $updateData = array_filter($data, fn($v) => !is_null($v) && $v !== '');
        if (empty($updateData)) {
            return response()->json([ 'success' => false, 'message' => 'No settings provided' ], 422);
        }

        // Get OLD boxes count BEFORE updating
        $oldBoxes = (int) optional($draw->settings)->boxes;
        $newBoxes = isset($updateData['boxes']) ? (int) $updateData['boxes'] : $oldBoxes;

        // Create or update settings
        if (!$draw->settings) {
            $createData = array_merge(['draw_id' => $draw->id], $updateData);
            $draw->settings()->create($createData);
            // Also recreate groups for new settings
            $this->recreateGroups($draw, $newBoxes);
        } else {
            $draw->settings()->update($updateData);

            // Recreate groups if boxes changed
            if (isset($updateData['boxes']) && $oldBoxes !== $newBoxes) {
                $this->recreateGroups($draw, $newBoxes);
            }
        }

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
    \Log::info("🔄 [recreateGroups] Starting", [
      'draw_id' => $draw->id,
      'new_num_groups' => $numGroups,
    ]);

    // 1. Collect all registration IDs currently in any group
    $allRegIds = \DB::table('draw_group_registrations')
      ->whereIn('draw_group_id', $draw->groups()->pluck('id'))
      ->pluck('registration_id')
      ->unique()
      ->values()
      ->toArray();

    \Log::info("📦 [recreateGroups] Found registrations to preserve", [
      'count' => count($allRegIds),
      'reg_ids' => $allRegIds,
    ]);

    // 2. Delete all existing groups (cascade deletes draw_group_registrations)
    $draw->groups()->delete();

    // 3. Create new groups A, B, C, ... up to numGroups
    $names = array_slice(range('A', 'Z'), 0, $numGroups);
    $firstGroup = null;

    foreach ($names as $name) {
      $group = $draw->groups()->create(['name' => $name]);
      if ($firstGroup === null) {
        $firstGroup = $group;
      }
    }

    \Log::info("✅ [recreateGroups] Created new groups", [
      'groups' => $names,
    ]);

    // 4. Move all players into Group A (first group)
    if ($firstGroup && count($allRegIds) > 0) {
      foreach ($allRegIds as $regId) {
        \DB::table('draw_group_registrations')->insert([
          'draw_group_id' => $firstGroup->id,
          'registration_id' => $regId,
        ]);
      }

      \Log::info("👥 [recreateGroups] Moved all players to Group {$firstGroup->name}", [
        'group_id' => $firstGroup->id,
        'player_count' => count($allRegIds),
      ]);
    }

    return $draw->groups()->get();
  }




  /**
   * Update playoff configuration (bracket sizes and positions)
   */
  public function updatePlayoffConfig(Request $request, Draw $draw)
  {
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
   * Generate playoff brackets from Round Robin standings
   */
  public function generatePlayoffBrackets(Request $request, Draw $draw)
  {
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
   * Get playoff brackets data for SVG rendering
   */
  public function getPlayoffBrackets(Request $request, Draw $draw)
  {
    try {
      $engine = new \App\Services\DynamicBracketEngine($draw);
      $svgData = $engine->build();

      return response()->json([
        'success' => true,
        'data' => $svgData,
      ]);
    } catch (\Exception $e) {
      \Log::error('[getPlayoffBrackets] Error', [
        'draw_id' => $draw->id,
        'error' => $e->getMessage(),
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
