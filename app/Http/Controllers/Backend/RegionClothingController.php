<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;

use App\Models\ClothingItemType;
use App\Models\ClothingSize;
use App\Models\TeamRegion;
use Illuminate\Http\Request;

class RegionClothingController extends Controller
{
  /**
   * Show clothing items (with sizes) for a region, and allow inline editing.
   * View: resources/views/admin/clothing/region_items.blade.php
   */
  public function edit(TeamRegion $region){
  
    $items = ClothingItemType::where('region_id', $region->id)->get();
   
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
 
    return view('backend.clothing.region-items', compact('region', 'items'));
  }

  /**
   * Create a new clothing item for a region.
   * POST /backend/region/{region}/clothing/items
   */
  public function storeItem(Request $request, TeamRegion $region)
  {
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
    // Ensure the item actually belongs to this region
    abort_if((int) $item->region_id !== (int) $region->id, 404);

    // If you want sizes to be deleted as well, uncomment next line:
    // ClothingSize::where('item_type', $item->id)->delete();

    $item->delete();

    return response()->json(['ok' => true]);
  }

  /**
   * Add a size to a clothing item for a region.
   * POST /backend/region/{region}/clothing/{item}/sizes
   */
  public function storeSize(Request $request, TeamRegion $region, ClothingItemType $item)
  {
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
    abort_if((int) $item->region_id !== (int) $region->id, 404);
    abort_if((int) $size->item_type !== (int) $item->id, 404);

    $size->delete();

    return response()->json(['ok' => true]);
  }

  public function orders($regionId)
  {
    $region = \App\Models\TeamRegion::findOrFail($regionId);

    $clothings = \App\Models\ClothingOrder::with([
      'items.itemType',
      'items.size',
      'player',
      'team'
    ])
      ->whereHas('team', fn($q) => $q->where('region_id', $regionId))
      ->orderByDesc('created_at')
      ->get();

    return view('backend.clothing.clothing-index', compact('region', 'clothings'));
  }


}
