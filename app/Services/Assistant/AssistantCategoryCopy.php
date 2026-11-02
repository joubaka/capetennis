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
        $message = trim($message, " \t\n\r.!?");
        $message = preg_replace('/(?:,?\s+(?:as well|too|please|thanks|thank you))+$/i', '', $message);
        $message = preg_replace('/,?\s+(?:without (?:creating )?duplicates|skip (?:categories that )?already exist|keeping (?:the )?same category settings)$/i', '', $message);
        $prefix = '(?:(?:can|could|would)\s+you\s+)?(?:please\s+)?(?:copy|reuse)\s+';
        if (! preg_match('/^'.$prefix.'(?:the\s+|all\s+)?categor(?:y|ies)\s+from\s+(.+?)\s+to\s+(.+)$/i', $message, $parts)
            && ! preg_match('/^'.$prefix.'(.+?)\s+categor(?:y|ies)\s+to\s+(.+)$/i', $message, $parts)) {
            return $this->clarify('Specify a source and destinations: Copy categories from event #123 to event #124 and event #125. You can also say copy the source event categories to the destination event.', 'category_parser_failure');
        }
        if (preg_match('/\b(without|except|excluding|with|keeping|leave|skip|change|changing|only|but)\b/i', $parts[1].' '.$parts[2])) {
            return $this->clarify('This tool copies all category definitions, fee overrides and ordering, and skips existing categories. Please request that copy without additional changes; I have not prepared a partial copy.', 'category_parser_failure');
        }
        $sourceResult = $this->find($parts[1], $actor);
        if (! isset($sourceResult['event'])) return $this->referenceClarification('source', $sourceResult);
        $source = $sourceResult['event'];
        $targetText = $this->spokenLegs(trim($parts[2]));
        // A sponsor's "and" belongs to its event name. Resolve the entire destination first.
        $whole = $this->find($targetText, $actor, $source);
        if (isset($whole['event'])) return ['source_id' => (int) $source->id, 'destination_ids' => [(int) $whole['event']->id]];
        if (! empty($whole['candidates'])) return $this->referenceClarification('destination', $whole);
        if (preg_match('/^legs?\s+(\d+(?:\s*(?:,|and|&)\s*\d+)*)$/i', $targetText, $legs)) {
            $targets = preg_split('/\s*(?:,|and|&)\s*/i', $legs[1]);
            foreach ($targets as &$target) {
                // Derive only the requested leg; retain the source's place/year/family tokens.
                $target = preg_replace('/\bleg\s*\d+\b/i', 'Leg '.$target, $this->spokenLegs($source->name), 1);
            }
            unset($target);
        } else {
            $targets = preg_split('/(?<=\d)\s*(?:,|;|\band\b)\s*/i', $targetText);
        }
        if (count($targets) > 10) return $this->clarify('Choose no more than ten destination events for one category copy.');
        $ids = [];
        foreach ($targets as $target) {
            $result = $this->find($target, $actor, $source);
            if (! isset($result['event'])) return $this->referenceClarification('destination', $result);
            $ids[] = (int) $result['event']->id;
        }
        return ['source_id' => (int) $source->id, 'destination_ids' => array_values(array_unique($ids))];
    }

    private function clarify(string $text, string $reason = 'category_reference_unresolved'): array
    {
        return ['clarification' => $text, 'reason_code' => $reason];
    }

    private function referenceClarification(string $kind, array $result): array
    {
        $choices = implode('; ', array_map(fn ($event) => '#'.$event->id.' '.mb_substr($event->name, 0, 120), $result['candidates'] ?? []));
        return $this->clarify('I could not identify one accessible '.$kind.' event. '.($choices !== '' ? 'Possible matches: '.$choices.'. ' : '').'Use the exact name including its year/place, or event #ID.',
            $choices === '' && empty($result['too_many']) ? 'category_reference_missing' : 'category_reference_unresolved');
    }

    private function spokenLegs(string $text): string
    {
        $numbers = ['one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6', 'seven' => '7', 'eight' => '8', 'nine' => '9', 'ten' => '10'];
        $number = '(?:one|two|three|four|five|six|seven|eight|nine|ten|\d+)';
        return preg_replace_callback('/\b(legs?)\s+('.$number.'(?:\s*(?:,|and|&)\s*'.$number.')*)\b/i',
            fn ($match) => $match[1].' '.strtr(mb_strtolower($match[2]), $numbers), $text);
    }

    private function eventTokens(string $text): array
    {
        $text = preg_replace('/^the\s+/i', '', trim($text));
        return array_values(array_unique(array_diff(explode(' ', $this->normalise($this->spokenLegs($text))), ['and', ''])));
    }

    private function normalise(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)));
    }

    private function find(string $name, User $actor, ?Event $source = null): array
    {
        $name = preg_replace('/^the\s+/i', '', trim($name));
        if (preg_match('/^event\s*#?(\d+)$/i', $name, $id)) {
            $events = Event::whereKey($id[1])->get();
        } else {
            $tokens = $this->eventTokens($name);
            if (! $tokens) return [];
            $query = Event::query();
            foreach ($tokens as $token) $query->where('name', 'like', '%'.$token.'%');
            $events = $query->orderBy('id')->limit(51)->get();
            if ($events->count() > 50) return ['too_many' => true];
            $events = $events->filter(function ($event) use ($tokens, $name, $source) {
                $candidate = $this->eventTokens($event->name);
                if (array_diff($tokens, $candidate)) return false;
                if (! array_diff($candidate, $tokens)) {
                    $explicit = array_filter($tokens, fn ($token) => ! in_array($token, ['spur', 'panarottis', 'leg', 'series', 'tennis', 'tournament'], true)
                        && (! ctype_digit($token) || preg_match('/^(?:19|20)\d\d$/D', $token)));
                    return ! $source || $explicit || $this->sameInferredFamily($tokens, $source, $event);
                }
                // Only a constrained sponsor/leg shorthand may omit additional event tokens.
                if (! array_intersect($tokens, ['spur', 'panarottis']) || ! preg_match('/\bleg\s*(\d+)\b/i', $this->spokenLegs($name), $leg)) return false;
                return preg_match('/\bleg\s*(\d+)\b/i', $this->spokenLegs($event->name), $other) && $other[1] === $leg[1]
                    && (! $source || $this->sameInferredFamily($tokens, $source, $event));
            });
        }
        $events = $events->filter(fn ($event) => Gate::forUser($actor)->allows('event.manage', $event) && Gate::forUser($actor)->allows('event-category.manage', $event));
        return $events->count() === 1 ? ['event' => $events->first()] : ['candidates' => $events->take(5)->values()->all()];
    }

    private function sameInferredFamily(array $query, Event $source, Event $target): bool
    {
        $from = $this->eventTokens($source->name);
        $to = $this->eventTokens($target->name);
        $years = fn ($tokens) => array_values(array_filter($tokens, fn ($token) => preg_match('/^(?:19|20)\d\d$/D', $token)));
        if (! $years($query)) {
            $sourceYear = $years($from)[0] ?? ($source->start_date ? substr((string) $source->start_date, 0, 4) : null);
            $targetYear = $years($to)[0] ?? ($target->start_date ? substr((string) $target->start_date, 0, 4) : null);
            if ($sourceYear !== null && $sourceYear !== $targetYear) return false;
        }
        $qualifiers = fn ($tokens) => array_values(array_filter($tokens, fn ($token) => ! ctype_digit($token)
            && ! in_array($token, ['spur', 'panarottis', 'leg', 'series', 'tennis', 'tournament'], true)));
        // No explicit destination place/family means retain the source's qualifiers.
        if (! $qualifiers($query) && array_diff($qualifiers($from), $to)) return false;
        return ! array_diff(array_intersect($from, ['spur', 'panarottis']), $to);
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
