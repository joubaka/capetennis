<?php

namespace App\Services\InterprovincialTrials;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\CategoryEventRegistration;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreviousResultsNominationService
{
    public function preview(Event $destination, array $mapping): array
    {
        $source = CategoryEvent::with('category', 'event')->findOrFail($mapping['source_category_event_id']);
        $target = CategoryEvent::with('category')->where('event_id', $destination->id)->findOrFail($mapping['category_event_id']);
        abort_if((int) $source->event_id === (int) $destination->id, 422, 'Choose a previous event.');
        abort_unless($source->event->start_date && $destination->start_date && $source->event->start_date->lt($destination->start_date), 422, 'The source event must precede this event.');
        abort_if((bool) $target->nominations_published, 422, 'This category is published. Private imports require an unpublished destination category.');
        // Saved final positions are authoritative. Never substitute provisional draw order,
        // nomination order, seeding, or an attached document for missing results.
        $results = DB::table('category_results')->where('event_id', $source->event_id)
            ->where('category_id', $source->category_id)->whereBetween('position', [1, $mapping['cutoff']])
            ->orderBy('position')->get();
        $fail = fn () => throw ValidationException::withMessages(['source_category_event_id' => 'Saved final results are missing, incomplete or ambiguous. Save and review final positions in the source event first; provisional draws and PDF attachments cannot be imported.']);
        if (CategoryEvent::where('event_id', $source->event_id)->where('category_id', $source->category_id)->count() !== 1) {
            $fail();
        }
        if ($results->pluck('position')->map(fn ($rank) => (int) $rank)->all() !== range(1, (int) $mapping['cutoff'])) {
            $fail();
        }
        $rows = [];
        foreach ($results as $result) {
            $membership = CategoryEventRegistration::where('category_event_id', $source->id)->where('registration_id', $result->registration_id)->exists();
            $registration = $membership ? Registration::with('players')->find($result->registration_id) : null;
            if (!$registration || $registration->players->count() !== 1 || $registration->competition_replacement) {
                $fail();
            }
            $player = $registration->players->sole();
            $rows[] = ['rank' => (int) $result->position, 'registration_id' => (int) $registration->id,
                'player_id' => (int) $player->id, 'name' => trim($player->name.' '.$player->surname)];
        }
        if (count(array_unique(array_column($rows, 'player_id'))) !== count($rows)) {
            $fail();
        }
        return ['source_event' => $source->event->name, 'source_category' => $source->category->name,
            'source_date' => $source->event->start_date->toDateString(), 'age_group_year' => (int) $mapping['age_group_year'],
            'category_event_id' => (int) $target->id, 'category' => $target->category->name, 'rows' => $rows];
    }

    public function save(Event $event, array $mapping, string $fingerprint): array
    {
        return DB::transaction(function () use ($event, $mapping, $fingerprint) {
            // Serialize against category publication and repeat imports.
            $target = CategoryEvent::where('event_id', $event->id)->whereKey($mapping['category_event_id'])->lockForUpdate()->firstOrFail();
            abort_if((bool) $target->nominations_published, 422, 'This category is published. Preview an unpublished destination category.');
            $preview = $this->preview($event, $mapping);
            abort_unless(hash_equals($fingerprint, hash('sha256', json_encode($preview))), 422, 'Results or profiles changed. Preview the selection again.');
            $added = 0;
            foreach ($preview['rows'] as $row) {
                $nomination = EventNomination::firstOrCreate(['event_id' => $event->id,
                    'category_event_id' => $mapping['category_event_id'], 'player_id' => $row['player_id']]);
                $added += (int) $nomination->wasRecentlyCreated;
            }
            return ['added' => $added, 'already_nominated' => count($preview['rows']) - $added];
        });
    }
}
