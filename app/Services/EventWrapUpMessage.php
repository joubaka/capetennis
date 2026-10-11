<?php

namespace App\Services;

use App\Models\{CategoryResult, Event};

class EventWrapUpMessage
{
    public function results(Event $event): string
    {
        if ($event->isTeam()) {
            if (! $event->standings_published) return '';
            $lines = [];
            foreach (app(EventStandingsService::class)->forEvent($event, [], true)['sections'] as $section) {
                $podium = collect($section['rows'])->filter(fn ($row) => $row['rank'] !== null && $row['rank'] <= 3 && ($row['played'] > 0 || $row['rubber_wins'] + $row['rubber_losses'] > 0));
                if ($podium->isEmpty()) continue;
                $lines[] = $section['draw']->drawName;
                foreach ($podium as $row) $lines[] = $row['rank'].'. '.$row['name'].' — '.$row['points'].' points';
                $lines[] = '';
            }
            return $lines ? "Published standings — podium summary\n".implode("\n", $lines)."\nFull published standings: ".route('frontend.events.standings', $event) : '';
        }
        if (! $event->results_published) return '';

        $categories = $event->eventCategories()->with('category')->get()->keyBy('category_id');
        $results = CategoryResult::where('event_id', $event->id)
            ->whereIn('category_id', $categories->keys())
            ->whereBetween('position', [1, 3])
            ->whereHas('registration.categoryEventRegistrations.categoryEvent', fn ($q) => $q->where('event_id', $event->id)->whereColumn('category_events.category_id', 'category_results.category_id'))
            ->with('registration.players')->orderBy('category_id')->orderBy('position')->get();
        $lines = [];
        foreach ($results->groupBy('category_id') as $categoryId => $positions) {
            $lines[] = $categories[$categoryId]->category?->name ?? 'Category';
            foreach ($positions as $result) {
                $lines[] = $result->position.'. '.$result->registration->players->pluck('full_name')->implode(' / ');
            }
            $lines[] = '';
        }

        return $lines ? "Published final results — podium summary\n".implode("\n", $lines)."\nFull published results: ".route('events.results', $event) : '';
    }

    public function fingerprint(Event $event): string
    {
        return hash('sha256', json_encode([(bool) ($event->isTeam() ? $event->standings_published : $event->results_published), $this->results($event)], JSON_THROW_ON_ERROR));
    }

    public function draft(Event $event): array
    {
        $results = $this->results($event);

        return [
            'subject' => $event->name.' — Thank you for a wonderful weekend',
            'body' => "Dear players and parents,\n\nThank you for being part of {$event->name}! We hope you enjoyed the weekend of tennis. Your enthusiasm, sportsmanship and support made it a special event.\n\nCongratulations to all our players, and a heartfelt thank you to the parents, coaches, officials and volunteers who helped make the weekend possible.\n\n".($results ? $results."\n\n" : '')."We look forward to welcoming you to our next event.\n\nWarm regards,\nThe Cape Tennis team",
            'results_available' => $results !== '',
            'results_fingerprint' => $this->fingerprint($event),
        ];
    }
}
