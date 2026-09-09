<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;

use App\Models\ClothingItemType;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\TeamRegion;
use App\Services\Clothing\RegionClothingCopyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegionClothingController extends Controller
{
  public function eventSetup(Event $event)
  {
    abort_unless($event->isTeam(), 404);
    $this->authorize('event-draw.view', $event);
    $event->load(['regions.clothingItems.sizes']);
    $sourceRegions = $this->clothingSourceRegions();
    $recommendedSources = $event->regions->mapWithKeys(function (TeamRegion $region) use ($sourceRegions) {
      $recommended = $sourceRegions
        ->where('id', '!=', $region->id)
        ->sortByDesc(fn (TeamRegion $candidate) => $this->sourceMatchScore($region, $candidate))
        ->first();
      if ($recommended && $this->sourceMatchScore($region, $recommended) < 10) $recommended = null;

      return [$region->id => $recommended];
    });

    return view('backend.clothing.event-setup', compact('event', 'recommendedSources'));
  }

  public function updateEventRegions(Request $request, Event $event)
  {
    abort_unless($event->isTeam(), 404);
    $this->authorize('event-draw.view', $event);

    $data = $request->validate([
      'region_ids' => ['nullable', 'array'],
      'region_ids.*' => ['integer', 'distinct'],
    ]);
    $eventRegionIds = $event->regions()->pluck('team_regions.id');
    $selectedIds = collect($data['region_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();

    if ($selectedIds->diff($eventRegionIds->map(fn ($id) => (int) $id))->isNotEmpty()) {
      abort(422, 'A selected region does not belong to this event.');
    }

    DB::transaction(function () use ($eventRegionIds, $selectedIds): void {
      TeamRegion::query()->whereIn('id', $eventRegionIds)->get()->each(function (TeamRegion $region) use ($selectedIds): void {
        $enabled = $selectedIds->contains((int) $region->id);
        $region->update([
          'clothing_admin' => $enabled ? 1 : 0,
          'clothing_order' => $enabled ? (int) $region->clothing_order : 0,
        ]);
      });
    });

    return redirect()->route('backend.event.clothing.index', $event)
      ->with('success', 'Online clothing regions updated. Catalogues and paid order history were kept.');
  }

  /**
   * Show clothing items (with sizes) for a region, and allow inline editing.
   * View: resources/views/admin/clothing/region_items.blade.php
   */
  public function edit(Request $request, TeamRegion $region){
    $this->authorize('region-clothing.manage', $region);
   
    // Load items for this region with sizes
    $items = ClothingItemType::with([
      'sizes' => function ($q) {
        $q->orderBy('ordering')->orderBy('size');
      }
    ])
      ->where('region_id', $region->id)
      ->orderByRaw('COALESCE(ordering, 9999)')
      ->orderBy('item_type_name')
      ->get();
 
    $sourceRegions = $this->clothingSourceRegions()->where('id', '!=', $region->id)->values();
    $requestedSourceId = $request->integer('source_region');
    $recommendedSource = $sourceRegions->sortByDesc(fn (TeamRegion $candidate) => $this->sourceMatchScore($region, $candidate))->first();
    if ($recommendedSource && $this->sourceMatchScore($region, $recommendedSource) < 10) {
      $recommendedSource = null;
    }
    $copySource = $requestedSourceId
      ? $sourceRegions->firstWhere('id', $requestedSourceId)
      : ($items->isEmpty() ? $recommendedSource : null);
    $copySource?->load(['clothingItems.sizes']);
    preg_match('/\b(20\d{2})\b/u', (string) $region->region_name, $targetYearMatch);
    $targetYear = isset($targetYearMatch[1]) ? (int) $targetYearMatch[1] : now()->year;

    return view('backend.clothing.region-items', compact('region', 'items', 'sourceRegions', 'copySource', 'targetYear'));
  }

  public function copyFromRegion(Request $request, TeamRegion $region, RegionClothingCopyService $service)
  {
    $this->authorize('region-clothing.manage', $region);
    abort_unless($region->usesOnlineClothingOrders(), 422, 'Select this region for online clothing orders before copying a catalogue.');
    $data = $request->validate([
      'source_region_id' => ['required', 'integer', Rule::exists('team_regions', 'id')->whereNot('id', $region->id)],
      'confirm_prices' => ['accepted'],
      'items' => ['required', 'array', 'min:1'],
      'items.*.selected' => ['nullable', 'boolean'],
      'items.*.source_item_id' => ['required', 'integer', 'distinct'],
      'items.*.item_type_name' => ['nullable', 'string', 'max:191'],
      'items.*.price' => ['nullable', 'integer', 'min:0'],
      'items.*.ordering' => ['nullable', 'integer', 'min:0'],
    ]);
    $result = $service->copy(
      $region,
      TeamRegion::findOrFail($data['source_region_id']),
      $data['items'],
      $request->user(),
      true
    );

    return redirect()->route('backend.region.clothing.edit', $region)
      ->with('success', "Copied {$result['created']} clothing item(s). {$result['skipped']} existing item(s) were left unchanged. Ordering remains closed.");
  }

  private function sourceMatchScore(TeamRegion $target, TeamRegion $candidate): int
  {
    $schoolLevel = static function (string $name): ?string {
      $normalised = mb_strtolower($name);

      if (preg_match('/\bprimary\b/u', $normalised)) return 'primary';
      if (preg_match('/\b(?:high|secondary)\b/u', $normalised)) return 'secondary';

      return null;
    };
    $tokens = static function (string $name): array {
      $normalised = mb_strtolower(preg_replace('/\b20\d{2}\b/u', '', $name) ?? $name);
      $parts = preg_split('/[^\pL\pN]+/u', $normalised, -1, PREG_SPLIT_NO_EMPTY) ?: [];
      return array_values(array_diff(array_unique($parts), ['schools', 'school', 'region']));
    };
    $targetSchoolLevel = $schoolLevel((string) $target->region_name);
    $candidateSchoolLevel = $schoolLevel((string) $candidate->region_name);
    if ($targetSchoolLevel !== null && $candidateSchoolLevel !== null && $targetSchoolLevel !== $candidateSchoolLevel) {
      return 0;
    }

    $targetTokens = $tokens((string) $target->region_name);
    $candidateTokens = $tokens((string) $candidate->region_name);
    preg_match('/\b(20\d{2})\b/u', (string) $target->region_name, $targetYearMatch);
    $targetYear = isset($targetYearMatch[1]) ? (int) $targetYearMatch[1] : now()->year;

    return count(array_intersect($targetTokens, $candidateTokens)) * 10
      + ($candidate->events->contains(fn ($event) => (int) $event->start_date?->format('Y') === $targetYear - 1) ? 5 : 0);
  }

  private function clothingSourceRegions()
  {
    return TeamRegion::query()
      ->whereHas('clothingItems')
      ->withCount('clothingItems')
      ->with(['events' => fn ($query) => $query->select('events.id', 'events.name', 'events.start_date')->orderByDesc('start_date')])
      ->orderByDesc('id')
      ->limit(50)
      ->get();
  }

  /**
   * Create a new clothing item for a region.
   * POST /backend/region/{region}/clothing/items
   */
  public function storeItem(Request $request, TeamRegion $region)
  {
    $this->authorize('region-clothing.manage', $region);
    $data = $request->validate([
      'item_type_name' => 'required|string|max:191',
      'price' => 'nullable|integer|min:0',
      'ordering' => 'nullable|integer|min:0',
    ]);

    $data['region_id'] = $region->id;

    $item = ClothingItemType::create($data);

    return response()->json([
      'ok' => true,
      'item' => $item,
    ]);
  }

  /**
   * Bulk update items (name, price, ordering) for this region.
   * PATCH /backend/region/{region}/clothing/items/bulk
   */
  public function bulkUpdate(Request $request, TeamRegion $region)
  {
    $this->authorize('region-clothing.manage', $region);
    $data = $request->validate([
      'items' => 'required|array|min:1',
      'items.*.id' => 'required|integer|exists:clothing_item_types,id',
      'items.*.item_type_name' => 'required|string|max:191',
      'items.*.price' => 'nullable|integer|min:0',
      'items.*.ordering' => 'nullable|integer|min:0',
    ]);

    // Only update rows that belong to this region
    foreach ($data['items'] as $row) {
      $item = ClothingItemType::where('region_id', $region->id)
        ->findOrFail($row['id']);

      $item->update([
        'item_type_name' => $row['item_type_name'],
        'price' => $row['price'] ?? 0,
        'ordering' => $row['ordering'] ?? null,
      ]);
    }

    return response()->json(['ok' => true]);
  }

  /**
   * Delete a clothing item (and optionally its sizes).
   * DELETE /backend/region/{region}/clothing/items/{item}
   */
  public function destroyItem(TeamRegion $region, ClothingItemType $item)
  {
    $this->authorize('region-clothing.manage', $region);
    // Ensure the item actually belongs to this region
    abort_if((int) $item->region_id !== (int) $region->id, 404);

    abort_if($item->orderItems()->exists(), 422, 'This item is used by an order and cannot be deleted.');

    \Illuminate\Support\Facades\DB::transaction(function () use ($item) {
      $item->sizes()->delete();
      $item->delete();
    });

    return response()->json(['ok' => true]);
  }

  /**
   * Add a size to a clothing item for a region.
   * POST /backend/region/{region}/clothing/{item}/sizes
   */
  public function storeSize(Request $request, TeamRegion $region, ClothingItemType $item)
  {
    $this->authorize('region-clothing.manage', $region);
    abort_if((int) $item->region_id !== (int) $region->id, 404);

    $data = $request->validate([
      'size' => 'required|string|max:50',
      'ordering' => 'nullable|integer|min:0',
    ]);

    $size = ClothingSize::create([
      'size' => $data['size'],
      'item_type' => $item->id,
      'ordering' => $data['ordering'] ?? null,
    ]);

    return response()->json([
      'ok' => true,
      'size' => $size,
    ]);
  }

  /**
   * Delete a size from a clothing item for a region.
   * DELETE /backend/region/{region}/clothing/{item}/sizes/{size}
   */
  public function destroySize(TeamRegion $region, ClothingItemType $item, ClothingSize $size)
  {
    $this->authorize('region-clothing.manage', $region);
    abort_if((int) $item->region_id !== (int) $region->id, 404);
    abort_if((int) $size->item_type !== (int) $item->id, 404);

    abort_if($size->orderItems()->exists(), 422, 'This size is used by an order and cannot be deleted.');
    $size->delete();

    return response()->json(['ok' => true]);
  }

  public function orders(TeamRegion $region)
  {
    $this->authorize('region-clothing.manage', $region);

    $clothings = \App\Models\ClothingOrder::with([
      'items.itemType',
      'items.size',
      'player',
      'team'
    ])
      ->whereHas('team', fn($q) => $q->where('region_id', $region->id))
      ->orderByDesc('created_at')
      ->get();

    return view('backend.clothing.clothing-index', compact('region', 'clothings'));
  }


}
