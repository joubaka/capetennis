<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Models\ClothingItemType;
use App\Models\ClothingSize;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegionClothingCopyService
{
    /**
     * @param array<int, array{selected?: mixed, source_item_id: mixed, item_type_name?: mixed, price?: mixed, ordering?: mixed}> $rows
     * @return array{created: int, skipped: int}
     */
    public function copy(TeamRegion $target, TeamRegion $source, array $rows, User $actor, bool $pricesConfirmed): array
    {
        if (! $pricesConfirmed) {
            throw ValidationException::withMessages(['confirm_prices' => 'Review and approve the selling prices before copying clothing.']);
        }
        if ((int) $target->id === (int) $source->id) {
            throw ValidationException::withMessages(['source_region_id' => 'Choose a different source region.']);
        }

        $selected = collect($rows)->filter(fn (array $row) => (bool) ($row['selected'] ?? false))->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Select at least one clothing item to copy.']);
        }

        return DB::transaction(function () use ($target, $source, $selected, $actor): array {
            $lockedTarget = TeamRegion::query()->lockForUpdate()->findOrFail($target->id);
            $lockedSource = TeamRegion::query()->lockForUpdate()->findOrFail($source->id);
            $sourceItems = ClothingItemType::query()->with('sizes')
                ->where('region_id', $lockedSource->id)
                ->whereIn('id', $selected->pluck('source_item_id')->map(fn ($id) => (int) $id))
                ->lockForUpdate()->get()->keyBy('id');
            if ($sourceItems->count() !== $selected->count()) {
                throw ValidationException::withMessages(['items' => 'One or more selected items do not belong to the source region.']);
            }

            $existingNames = ClothingItemType::query()->where('region_id', $lockedTarget->id)
                ->get(['item_type_name'])->mapWithKeys(fn (ClothingItemType $item) => [
                    mb_strtolower(trim((string) $item->item_type_name)) => true,
                ]);
            $created = 0;
            $skipped = 0;
            foreach ($selected as $row) {
                $sourceItem = $sourceItems->get((int) $row['source_item_id']);
                $name = trim((string) ($row['item_type_name'] ?? ''));
                $price = round((float) ($row['price'] ?? -1), 2);
                $ordering = filled($row['ordering'] ?? null) ? (int) $row['ordering'] : null;
                if ($name === '' || $price < 0) {
                    throw ValidationException::withMessages([
                        'items' => "Review the name and selling price for {$sourceItem->item_type_name}.",
                    ]);
                }

                $nameKey = mb_strtolower($name);
                if ($existingNames->has($nameKey)) {
                    $skipped++;
                    continue;
                }

                $newItem = ClothingItemType::create([
                    'item_type_name' => $name,
                    'price' => $price,
                    'region_id' => $lockedTarget->id,
                    'ordering' => $ordering,
                ]);
                $seenSizes = [];
                foreach ($sourceItem->sizes->sortBy([['ordering', 'asc'], ['id', 'asc']]) as $size) {
                    $sizeName = trim((string) $size->size);
                    $sizeKey = mb_strtolower($sizeName);
                    if ($sizeName === '' || isset($seenSizes[$sizeKey])) continue;
                    ClothingSize::create([
                        'size' => $sizeName,
                        'item_type' => $newItem->id,
                        'ordering' => $size->ordering,
                    ]);
                    $seenSizes[$sizeKey] = true;
                }
                $existingNames->put($nameKey, true);
                $created++;
            }

            $lockedTarget->forceFill([
                'clothing_admin' => true,
                'clothing_order' => false,
            ])->save();

            activity('clothing')->performedOn($lockedTarget)->causedBy($actor)
                ->withProperties([
                    'source_region_id' => $lockedSource->id,
                    'created_items' => $created,
                    'skipped_existing_items' => $skipped,
                    'reviewed_prices' => $selected->map(fn (array $row) => [
                        'source_item_id' => (int) $row['source_item_id'],
                        'name' => trim((string) ($row['item_type_name'] ?? '')),
                        'price' => round((float) ($row['price'] ?? 0), 2),
                    ])->all(),
                ])->log('copied reviewed clothing setup from previous region');

            return compact('created', 'skipped');
        });
    }
}
