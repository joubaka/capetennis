<?php

namespace App\Services;

use App\Models\{CategoryResult, Event};
use Illuminate\Support\Str;

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
        $context = $this->context($event);
        $results = $event->published ? $this->results($event) : '';
        $paragraphs = [
            'Dear players and families,',
            "Thank you for being part of {$event->name}{$context['when']}! Well done to everyone who took part, and thank you for the effort and sportsmanship you brought to the event.",
        ];
        if ($context['venues'] !== '') {
            $paragraphs[] = 'Event venues: '.$context['venues'].'.';
        }
        if ($context['disrupted']) {
            $paragraphs[] = 'Thank you for your patience while we adjusted the programme during the event.';
        }
        if ($results !== '') {
            $paragraphs[] = $results;
        }
        $paragraphs[] = 'Thank you also to the families, coaches, officials and volunteers who helped make the event possible. We look forward to seeing you again.';
        $paragraphs[] = "Warm regards,\nThe Cape Tennis team";

        return [
            'subject' => Str::limit('Thank you for being part of '.$event->name, 197),
            'body' => implode("\n\n", $paragraphs),
            'results_available' => $results !== '',
            'results_fingerprint' => $this->fingerprint($event),
            'context_fingerprint' => $context['fingerprint'],
            'sources' => [...$context['sources'], ...($results !== '' ? ['Published results or standings'] : [])],
            'weather_review' => $context['weather_review'],
            'send_state' => $this->sendState($event),
        ];
    }

    public function contextFingerprint(Event $event): string
    {
        return $this->context($event)['fingerprint'];
    }

    public function sendState(Event $event): string
    {
        $status = strtolower(trim((string) $event->status));
        if (in_array($status, ['cancelled', 'canceled', 'postponed'], true)) {
            return 'unavailable';
        }
        if (app(EventLifecycleService::class)->snapshot($event)['state'] === 'completed') {
            return 'ready';
        }

        $today = now()->toDateString();
        $lastDay = ($event->end_date ?? $event->start_date)?->toDateString();
        if (! $lastDay || $lastDay > $today) {
            return 'too_early';
        }

        return $lastDay === $today ? 'same_day' : 'ready';
    }

    private function context(Event $event): array
    {
        $start = $event->start_date?->format('j F Y');
        $end = $event->end_date?->format('j F Y');
        $date = $start && $end && $start !== $end ? $start.' to '.$end : ($start ?? $end);
        $when = $date ? ' on '.$date : '';
        $venues = $event->venues()->orderBy('venues.name')->pluck('venues.name')->filter()->unique()->implode(', ');
        $sources = ['Event details: '.$event->name.$when];
        if ($venues !== '') {
            $sources[] = 'Listed venues: '.$venues;
        }
        $weatherReview = [];
        $disrupted = false;
        $revisions = [];
        if ($event->published) {
            $announcements = $event->announcements()->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get();
            foreach ($announcements as $announcement) {
                $title = $announcement->title ?: 'Event update';
                $message = preg_replace('/<\/?(?:p|li|div|br)\b[^>]*>/i', "\n", $announcement->message ?? '');
                $content = $title."\n".html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! preg_match('/\b(?:weather|rain|wet courts?|delay(?:ed|s)?|venue change|relocated|moved|suspended|no play before)\b/i', $content)) {
                    continue;
                }
                $revisions[] = $announcement->readRevision();
                if ($this->confirmsDisruption($content)) {
                    $disrupted = true;
                    $sources[] = 'Event announcement: '.$title;
                } else {
                    $weatherReview[] = $title;
                }
            }
        }

        return [
            'when' => $when,
            'venues' => $venues,
            'sources' => $sources,
            'weather_review' => $weatherReview,
            'disrupted' => $disrupted,
            'fingerprint' => hash('sha256', json_encode([1, $event->id, $event->published, $event->name, $start, $end, $venues, $this->sendState($event) === 'unavailable', $revisions], JSON_THROW_ON_ERROR)),
        ];
    }

    private function confirmsDisruption(string $content): bool
    {
        if (preg_match('/\b(?:ignore|disregard|superseded|do not apply)\b/i', $content)) {
            return false;
        }
        foreach (preg_split('/\R+|(?<=[.!?])\s+/', $content) as $sentence) {
            if (preg_match('/\b(?:if|may|might|could|forecast|weather permitting|possible|no delays?|without delay|last year|previous event|on schedule|as scheduled)\b/i', $sentence)) {
                continue;
            }
            if (preg_match('/\b(?:play|matches|fixtures|programme|start(?: of play)?)\s+(?:has been|have been|was|were|is|are)\s+(?:now\s+)?(?:delayed|suspended|moved|relocated)\b|\bno play before\s+\d/i', $sentence)) {
                return true;
            }
        }

        return false;
    }
}
