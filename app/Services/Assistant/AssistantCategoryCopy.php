<?php

namespace App\Services\Assistant;

use App\Models\{Category, CategoryEvent, Event, User};
use Illuminate\Support\Facades\Gate;
use SportStack\Assistant\ProposalFingerprint;

final class AssistantCategoryCopy
{
    public function recognises(string $message): bool
    {
        return (bool) preg_match('/\b(copy|reuse)\b.*\bcategor(?:y|ies)\b/i', $message);
    }

    public function resolve(string $message, User $actor): array
    {
        if (! preg_match('/\bcategor(?:y|ies)\s+from\s+(.+?)\s+to\s+(.+?)(?=[.;]|,?\s+(?:with|without|keeping|leave|skip)\b|$)/i', $message, $parts, PREG_OFFSET_CAPTURE)) {
            return ['clarification' => 'Specify a source and destinations: Copy categories from event #123 to event #124 and event #125. You can also use exact event names, or Leg 1 to Legs 2 and 3.'];
        }
        $suffix = trim(substr($message, $parts[0][1] + strlen($parts[0][0])), " \t\n\r,.;");
        if ($suffix !== '' && ! preg_match('/^(?:without (?:creating )?duplicates|skip (?:categories that )?already exist|keeping (?:the )?same category settings)\.?$/i', $suffix)) {
            return ['clarification' => 'This tool copies all category definitions, fee overrides and ordering, and skips existing categories. Please request that copy without additional changes; I have not prepared a partial copy.'];
        }
        $source = $this->find(trim($parts[1][0]), $actor);
        if (! $source) return ['clarification' => 'I could not identify one accessible source event. Use its exact name or event #ID.'];
        $base = preg_replace('/\bleg\s*\d+\b.*$/i', '', $source->name);
        $targets = preg_split('/\s*(?:,|\band\b|&)\s*/i', trim($parts[2][0]));
        if (count($targets) > 10) return ['clarification' => 'Choose no more than ten destination events for one category copy.'];
        $ids = [];
        foreach ($targets as $target) {
            $target = trim($target);
            if (preg_match('/^(?:legs?\s*)?(\d+)$/i', $target, $leg)) $target = trim($base).' Leg '.$leg[1];
            $event = $this->find($target, $actor);
            if (! $event) return ['clarification' => 'I could not identify every destination unambiguously. Use exact event names or event #IDs for each destination.'];
            $ids[] = (int) $event->id;
        }
        return ['source_id' => (int) $source->id, 'destination_ids' => array_values(array_unique($ids))];
    }

    private function normalise(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    private function find(string $name, User $actor): ?Event
    {
        if (preg_match('/^event\s*#?(\d+)$/i', $name, $id)) {
            $events = Event::whereKey($id[1])->get();
        } else {
            $tokens = explode(' ', $this->normalise($name));
            $query = Event::query();
            foreach ($tokens as $token) $query->where('name', 'like', '%'.$token.'%');
            $events = $query->orderBy('id')->limit(51)->get();
            if ($events->count() > 50) return null;
            $events = $events->filter(fn ($event) => $this->normalise($event->name) === $this->normalise($name));
        }
        $events = $events->filter(fn ($event) => Gate::forUser($actor)->allows('event.manage', $event) && Gate::forUser($actor)->allows('event-category.manage', $event));
        return $events->count() === 1 ? $events->first() : null;
    }

    public function review(User $actor, int $sourceId, array $destinationIds, bool $lock = false): array
    {
        abort_if(! $destinationIds || count($destinationIds) > 10 || in_array($sourceId, $destinationIds), 422, 'Choose between one and ten different destination events, excluding the source.');
        $ids = array_values(array_unique([$sourceId, ...$destinationIds]));
        $query = Event::whereIn('id', $ids)->orderBy('id');
        $events = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        abort_unless($events->count() === count($ids), 404);
        foreach ($events as $event) {
            Gate::forUser($actor)->authorize('event.manage', $event);
            Gate::forUser($actor)->authorize('event-category.manage', $event);
            if ($event->id !== $sourceId) Gate::forUser($actor)->authorize('event.settings.manage', $event);
        }
        $query = CategoryEvent::whereIn('event_id', $ids)->orderBy('id')->limit(1001);
        $rows = ($lock ? $query->lockForUpdate() : $query)->get();
        abort_if($rows->count() > 1000, 422, 'Choose fewer events or categories for one copy preview.');
        $categoryQuery = Category::whereIn('id', $rows->pluck('category_id'))->orderBy('id');
        $categories = ($lock ? $categoryQuery->lockForUpdate() : $categoryQuery)->get()->keyBy('id');
        $source = $rows->where('event_id', $sourceId)->unique('category_id');
        abort_if($source->isEmpty(), 422, 'The source event has no categories to copy.');
        $names = $source->map(fn ($row) => $this->normalise($categories->get($row->category_id)?->name ?? ''));
        abort_if($names->unique()->count() !== $names->count(), 422, 'The source contains categories with equivalent names. Resolve them before copying.');
        $destinations = [];
        $create = [];
        foreach ($destinationIds as $id) {
            $existing = $rows->where('event_id', $id);
            $items = [];
            foreach ($source as $row) {
                $category = $categories->get($row->category_id);
                abort_unless($category, 422, 'A source category is missing. Repair it before copying.');
                $kept = $existing->first(fn ($other) => $other->category_id === $row->category_id || $this->normalise($categories->get($other->category_id)?->name ?? '') === $this->normalise($category->name));
                $duplicate = $kept !== null;
                abort_if(! $duplicate && $events[$id]->isInterprovincialTrials() && $row->entry_fee !== null, 422, 'Trials use the event fee for every category. A source fee override cannot be copied there.');
                $items[] = ['name' => $category->name, 'category_id' => (int) $category->id, 'entry_fee' => $row->entry_fee, 'ordering' => $row->ordering, 'action' => $duplicate ? 'skip_existing' : 'add', 'result_entry_fee' => $duplicate ? $kept->entry_fee : $row->entry_fee, 'result_effective_fee' => ($duplicate ? $kept->entry_fee : $row->entry_fee) ?? $events[$id]->entryFee, 'result_ordering' => $duplicate ? $kept->ordering : $row->ordering];
                if (! $duplicate) $create[] = ['event_id' => (int) $id, 'category_id' => (int) $category->id, 'entry_fee' => $row->entry_fee, 'ordering' => $row->ordering];
            }
            $destinations[] = ['id' => (int) $id, 'name' => $events[$id]->name, 'categories' => $items];
        }
        $preview = ['source' => ['id' => $sourceId, 'name' => $events[$sourceId]->name], 'destinations' => $destinations, 'count' => count($create), 'scope' => 'Copy category definitions, fee overrides and ordering only. Existing categories stay unchanged. Registrations, nominations, publication, locks, draws and results are not copied.'];
        return ['preview' => $preview, 'create' => $create, 'revision' => ProposalFingerprint::make(['events' => $events->map->getAttributes()->all(), 'rows' => $rows->map->getAttributes()->all(), 'categories' => $categories->map->getAttributes()->all(), 'preview' => $preview])];
    }

    public function apply(User $actor, array $payload): array
    {
        $review = $this->review($actor, $payload['source_id'], $payload['destination_ids'], true);
        abort_unless(hash_equals($payload['revision'], $review['revision']), 409, 'The events or categories changed. Review a new copy preview.');
        $created = [];
        foreach ($review['create'] as $attributes) $created[] = CategoryEvent::firstOrCreate(['event_id' => $attributes['event_id'], 'category_id' => $attributes['category_id']], $attributes)->id;
        return ['count' => count($created), 'category_event_ids' => $created];
    }
}
