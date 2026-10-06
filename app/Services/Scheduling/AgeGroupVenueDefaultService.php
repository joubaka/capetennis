<?php

namespace App\Services\Scheduling;

use App\Models\{CategoryEvent, Draw, Event, TeamCategory, TeamFixture, OrderOfPlay, User, Venue};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AgeGroupVenueDefaultService
{
    public function groups(Event $event): \Illuminate\Support\Collection
    {
        return $event->draws->map(function ($draw) {
            $key = $this->key($draw);
            return ['draw' => $draw, 'key' => $key, 'label' => $key ? 'Under '.$key['age'].' '.ucfirst($key['gender']) : 'Other draws'];
        })->sortBy(fn ($row) => sprintf('%03d-%d-%010d', $row['key']['age'] ?? 999,
            ($row['key']['gender'] ?? '') === 'girls' ? 1 : 0, $row['draw']->id))->groupBy('label')
            ->map(fn ($rows) => $rows->pluck('draw'));
    }

    /** Prefer category metadata; legacy titles require an exact configured category and discipline. */
    public function key(Draw $draw): ?array
    {
        $ids = $draw->team_draw_selection['category_ids'] ?? ($draw->category_event_id ? [$draw->category_event_id] : []);
        $names = collect();
        if ($ids) {
            $categories = CategoryEvent::with('category')->where('event_id', $draw->event_id)->whereIn('id', $ids)->get();
            if ($categories->count() !== count(array_unique($ids))) return null;
            $names = $categories->map(fn ($category) => $category->category?->name);
        } elseif ($draw->team_category_id) {
            $category = TeamCategory::where('eventId', $draw->event_id)->find($draw->team_category_id);
            if ($category) $names->push($category->ageGroup.' '.$draw->gender);
        } else {
            // Older generated draws did not persist category IDs. Accept only their
            // exact configured event-category + known discipline naming convention.
            $names = CategoryEvent::with('category')->where('event_id', $draw->event_id)->get()
                ->map(fn ($category) => $category->category?->name)->filter(fn ($name) => $name
                    && preg_match('/^'.preg_quote(trim($name), '/').'\s*(?:[–-]\s*)?(?:Singles|Doubles|Reverse Singles|Singles Reverse)$/iu', trim($draw->drawName)))->values();
            if ($names->count() !== 1) return null;
        }
        $keys = $names->map(function ($name) {
            if (! preg_match('/^(?:u\s*\/?\s*|under\s+)(\d+)\s*(boys|girls)\b/i', trim((string) $name), $matches)) return null;
            return ['age' => (int) $matches[1], 'gender' => strtolower($matches[2])];
        });
        if ($keys->isEmpty() || $keys->contains(null) || $keys->unique(fn ($key) => $key['age'].'|'.$key['gender'])->count() !== 1) return null;
        return $keys->first();
    }

    public function inherit(Draw $draw): void
    {
        $key = $this->key($draw);
        if (! $key) return;
        $default = DB::table('event_age_group_venue_defaults')->where('event_id', $draw->event_id)->where($key)->first();
        if (! $default) return;
        $venues = json_decode($default->venues, true, 512, JSON_THROW_ON_ERROR);
        $current = DB::table('event_venues')->where('event_id', $draw->event_id)->pluck('num_courts', 'venue_id');
        $venues = collect($venues)->filter(fn ($allocation, $venueId) => $current->has($venueId))
            ->map(fn ($allocation, $venueId) => ['num_courts' => min((int) $allocation['num_courts'], max(1, (int) $current[$venueId]))])->all();
        $draw->venues()->sync($venues);
        $draw->unsetRelation('venues');
    }

    public function save(Draw $source, array $venues, User $actor): array
    {
        return DB::transaction(function () use ($source, $venues, $actor) {
            $event = Event::whereKey($source->event_id)->lockForUpdate()->firstOrFail();
            $draws = $event->draws()->orderBy('id')->lockForUpdate()->get();
            $source = $draws->firstWhere('id', $source->id);
            $key = $source ? $this->key($source) : null;
            if (! $key) $this->invalid('This draw needs one unambiguous age group and gender before venues can become a default.');
            Gate::forUser($actor)->authorize('event.manage', $event);
            $allowed = $event->venues()->pluck('venues.id')->merge(DB::table('draw_venues')->whereIn('draw_id', $draws->pluck('id'))->pluck('venue_id'))->unique()->map(fn ($id) => (int) $id)->all();
            $newVenueIds = array_diff(array_keys($venues), $allowed);
            $available = Venue::whereIn('id', $newVenueIds)->where(fn ($query) => $query->whereNull('event_id')->orWhere('event_id', $event->id))->pluck('id')->all();
            if (array_diff($newVenueIds, $available)) $this->invalid('A selected venue belongs to another event.');
            $matching = $draws->filter(fn ($draw) => $this->key($draw) === $key);
            foreach ($matching as $draw) {
                if (! Gate::forUser($actor)->allows('update', $draw)) $this->invalid($draw->drawName.' is locked, published, or unavailable for editing. No defaults were changed.');
                $before = $draw->venues()->get()->mapWithKeys(fn ($venue) => [(int) $venue->id => ['num_courts' => (int) $venue->pivot->num_courts]])->all();
                if ($before == $venues) continue;
                // Keep physical allocations and saved bookings intact. Changes require the schedule planner's explicit workflow.
                if (DB::table('draw_venue_court_allocations')->where('draw_id', $draw->id)->exists()
                    || OrderOfPlay::whereHas('fixture', fn ($query) => $query->where('draw_id', $draw->id))->exists()
                    || TeamFixture::where('draw_id', $draw->id)->where(fn ($query) => $query->whereNotNull('venue_id')->orWhereNotNull('scheduled_at')->orWhere('scheduled', 1))->exists()) {
                    $this->invalid($draw->drawName.' has saved court allocations or bookings. Review them in the venue schedule before changing its venues. No defaults were changed.');
                }
            }
            foreach ($matching as $draw) $draw->venues()->sync($venues);
            $registered = $event->venues()->pluck('venues.id')->all();
            foreach (array_diff(array_keys($venues), $registered) as $venueId) $event->venues()->syncWithoutDetaching([$venueId => $venues[$venueId]]);
            DB::table('event_age_group_venue_defaults')->updateOrInsert(['event_id' => $event->id] + $key,
                ['venues' => json_encode($venues, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            return $matching->pluck('id')->all();
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['age_group_default' => $message]);
    }
}
