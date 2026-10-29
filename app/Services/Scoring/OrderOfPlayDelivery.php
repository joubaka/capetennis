<?php

namespace App\Services\Scoring;

use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, Event, TeamFixture, User, Venue};
use App\Services\BulkMailDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class OrderOfPlayDelivery
{
    public function rows(Collection $matches): array
    {
        return $matches->map(function ($match) {
            $team = $match instanceof TeamFixture;
            $schedule = $team ? $match->scheduled_at : $match->orderOfPlay?->time;
            if (! $schedule) return null;
            $names = $team ? collect(['home', 'away'])->map(function ($side) use ($match) {
                $players = collect($match->lineup_display[$side]['players'] ?? [])->pluck('name');
                return $players->isNotEmpty() ? $players->join(' + ')
                    : ($match->{$side.'Team'}?->name ?? $match->{$side === 'home' ? 'region1Name' : 'region2Name'}?->name ?? 'To be decided');
            })->all() : $match->scoring_participant_labels;
            return ['key' => ($team ? 'team:' : 'individual:').$match->id, 'draw_id' => (int) $match->draw_id,
                'draw' => $match->draw->drawName, 'match' => $match->match_nr ?: $match->id,
                'time' => \Carbon\Carbon::parse($schedule)->format('Y-m-d H:i:s'),
                'court' => (string) ($team ? $match->court_label : $match->orderOfPlay?->court),
                'players' => $names, 'result' => $match->fixtureResults->sortBy('set_nr')->map(fn ($set) => $team
                    ? $set->team1_score.'-'.$set->team2_score : $set->registration1_score.'-'.$set->registration2_score)->join(', ')];
        })->filter()->values()->all();
    }

    public function reviewToken(Event $event, User $actor, Venue $venue, array $scope, array $rows): string
    {
        return hash_hmac('sha256', json_encode([$event->id, $actor->id, $venue->id, $scope, $rows], JSON_THROW_ON_ERROR), config('app.key'));
    }

    public function allowed(Event $event, User $actor, int $venueId, array $drawIds): bool
    {
        if (! $actor->can('event.score', $event)
            || ($actor->is_event_score_keeper($event->id) && ! $actor->canScoreVenue($event->id, $venueId))
            || ! $drawIds || $event->draws()->whereIn('id', $drawIds)->count() !== count($drawIds)) return false;

        return $event->venues()->whereKey($venueId)->exists()
            || $event->draws()->whereHas('venues', fn ($query) => $query->where('venues.id', $venueId))->exists()
            || DB::table('order_of_plays')->where('venue_id', $venueId)->whereIn('draw_id', $event->draws()->select('id'))->exists()
            || TeamFixture::where('venue_id', $venueId)->whereIn('draw_id', $event->draws()->select('id'))->exists()
            || DB::table('published_schedule_assignments')->where('event_id', $event->id)->where('venue_id', $venueId)->exists();
    }

    public function pdf(Event $event, Venue $venue, array $scope, array $rows): string
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('frontend.scoring.order-of-play-pdf', compact('event', 'venue', 'scope', 'rows'))
            ->setPaper('A4', 'landscape')->output();
    }

    public function queue(Event $event, User $actor, Venue $venue, array $scope, array $rows, string $recipient, string $requestKey, string $reviewToken): array
    {
        abort_unless($this->allowed($event, $actor, $venue->id, $scope['draw_ids']), 403);
        abort_unless(hash_equals($this->reviewToken($event, $actor, $venue, $scope, $rows), $reviewToken), 409,
            'The order of play changed. Open Email order of play again to review the current copy.');
        abort_unless($rows && count($rows) < 500, 422, 'Choose a day or draw with between 1 and 499 scheduled matches.');
        $recipient = strtolower(trim($recipient));
        $log = DB::transaction(function () use ($event, $actor, $venue, $scope, $rows, $recipient, $requestKey, $reviewToken) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $campaign = 'order-of-play:'.$requestKey;
            $existing = BulkEmailLog::where('mail_type', 'scoring_order_of_play')->where('related_type', Event::class)
                ->where('related_id', $event->id)->where('payload->created_by', $actor->id)->where('payload->campaign_key', $campaign)->first();
            if ($existing) {
                abort_unless($existing->recipient_email === $recipient && ($existing->payload['review_token'] ?? null) === $reviewToken, 409,
                    'This send request was already used for another recipient or schedule.');
                return $existing;
            }
            $sourceLabel = $scope['source'] === 'working' ? 'Working schedule — private staff copy' : 'Published schedule';
            $payload = ['event_id' => $event->id, 'created_by' => $actor->id, 'venue_id' => $venue->id, 'scope' => $scope,
                'campaign_key' => $campaign, 'review_token' => $reviewToken,
                'subject' => 'Order of play: '.$event->name.' — '.$venue->name,
                'body' => '<p>The order of play for '.e($event->name).' at '.e($venue->name).' is attached.</p><p>'.e($sourceLabel).' · '.e($scope['date'] ?? 'All saved days').' · '.count($rows).' matches.</p>',
                'filename' => 'order-of-play.pdf', 'pdf' => base64_encode($this->pdf($event, $venue, $scope, $rows))];
            $payload['delivery_signature'] = $this->signature($payload, $recipient);
            app(BulkMailDispatcher::class)->dispatch('scoring_order_of_play', $event, [$recipient], $payload);
            return BulkEmailLog::where('mail_type', 'scoring_order_of_play')->where('related_id', $event->id)
                ->where('payload->created_by', $actor->id)->where('payload->campaign_key', $campaign)->firstOrFail();
        });
        $log->refresh();
        $failed = in_array($log->status, ['failed', 'skipped', 'acceptance_unknown'], true);
        return ['failed' => $failed, 'message' => $failed ? 'The email could not be queued. Check mail history before retrying.' : 'Order of play queued for '.$recipient.'.'];
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        $payload = $log->payload;
        $event = Event::find($payload['event_id'] ?? 0);
        $actor = User::find($payload['created_by'] ?? 0);
        if ($log->mail_type !== 'scoring_order_of_play' || ! $event || ! $actor
            || $log->related_type !== Event::class || (int) $log->related_id !== (int) $event->id
            || ! $this->allowed($event, $actor, (int) ($payload['venue_id'] ?? 0), $payload['scope']['draw_ids'] ?? [])
            || ! hash_equals($this->signature($payload, $log->recipient_email), $payload['delivery_signature'] ?? '')) return null;
        $pdf = base64_decode($payload['pdf'] ?? '', true);
        if (! $pdf || ! str_starts_with($pdf, '%PDF-')) return null;
        return (new BulkEventMail($payload['subject'], $payload['body'], config('mail.from.name', 'Cape Tennis'), config('mail.from.address')))
            ->attachData($pdf, $payload['filename'], ['mime' => 'application/pdf'])->with('scoring_order_of_play_log_id', $log->id);
    }

    private function signature(array $payload, string $recipient): string
    {
        return hash_hmac('sha256', json_encode([$recipient, $payload['event_id'] ?? null, $payload['created_by'] ?? null,
            $payload['venue_id'] ?? null, $payload['scope']['source'] ?? null, $payload['scope']['date'] ?? null,
            $payload['scope']['draw_ids'] ?? [], $payload['campaign_key'] ?? null, $payload['review_token'] ?? null,
            $payload['subject'] ?? null, $payload['body'] ?? null, $payload['filename'] ?? null, $payload['pdf'] ?? null,
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
