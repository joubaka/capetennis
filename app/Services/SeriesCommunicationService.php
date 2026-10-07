<?php

namespace App\Services;

use App\Models\{BulkEmailLog, Event, EventCommunicationBatch, Series, User};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** One reviewed intent, with independent event messages and durable reservations. */
class SeriesCommunicationService
{
    public function __construct(private EventCommunicationService $communications, private TeamSelectionContactService $contacts, private BulkMailDispatcher $dispatcher) {}

    private function events(Series $series, User $actor): Collection
    {
        $events = $series->events()->orderBy('id')->get();
        abort_if($events->isEmpty(), 422, 'This series has no events.');
        foreach ($events as $event) {
            Gate::forUser($actor)->authorize('event-email.send', $event);
            abort_unless($this->communications->managesWholeEvent($event, $actor), 403);
            $this->communications->regions($event, $actor);
        }

        return $events;
    }

    private function fingerprint(array $value): string
    {
        $normalize = function (array $items) use (&$normalize): array {
            if (! array_is_list($items)) {
                ksort($items);
            }
            foreach ($items as $key => $item) {
                if (is_array($item)) {
                    $items[$key] = $normalize($item);
                }
            }
            return $items;
        };

        return hash('sha256', json_encode($normalize($value), JSON_THROW_ON_ERROR));
    }

    private function eventPlan(Event $event, string $subject, string $body): array
    {
        $byEmail = collect();
        $issues = collect();
        foreach ($event->registrations()->with(['players.user', 'players.users', 'categoryEvent.category'])->get() as $entry) {
            foreach ($entry->players as $player) {
                $emails = $this->contacts->emails($player);
                if ($emails->isEmpty()) {
                    $issues->push($player->full_name.' - no valid contact');
                }
                $detail = $player->full_name.' - '.($entry->categoryEvent?->category?->name ?? '');
                foreach ($emails as $email) {
                    $byEmail->put($email, [...($byEmail->get($email, ['name'=>$player->full_name, 'details'=>[]])), 'details'=>[...($byEmail->get($email)['details'] ?? []), $detail]]);
                }
            }
        }
        $recipients = $byEmail->map(fn ($person,$email) => [
            'email'=>$email, 'name'=>$person['name'], 'kind'=>'players', 'subject'=>$subject,
            'html'=>nl2br(e($body."\n\nEvent: ".$event->name."\nPlayer details:\n".collect($person['details'])->unique()->sort()->implode("\n"))),
        ])->sortBy('email')->values()->all();
        $plan = ['recipients'=>$recipients, 'issues'=>$issues->unique()->sort()->values()->all()];
        $plan['fingerprint'] = $this->fingerprint([$event->id,$subject,$body,$plan]);

        return $plan;
    }

    public function batches(Series $series, User $actor, string $intent): Collection
    {
        $events = $this->events($series, $actor);
        $batches = EventCommunicationBatch::with('event')->where('created_by',$actor->id)
            ->where('options->source','series_compose')->where('options->series_id',$series->id)->where('options->intent',$intent)->orderBy('event_id')->get();
        abort_if($batches->isEmpty(),404);
        abort_unless($batches->pluck('event_id')->map(fn ($id) => (int) $id)->all() === $events->pluck('id')->map(fn ($id) => (int) $id)->all(), 422, 'Series events changed. Prepare a fresh review.');

        return $batches;
    }

    public function preview(Series $series, User $actor, string $intent, string $subject, string $body, string $fromName, string $replyTo): Collection
    {
        \App\Services\CommunicationSender::resolve(['from_name' => $fromName, 'reply_to' => $replyTo], $actor);
        $events = $this->events($series,$actor);
        $body = trim(strip_tags(preg_replace('/<\/(p|div|li)>|<br\s*\/?\s*>/i',"\n",$body)));
        $options = ['source'=>'series_compose','series_id'=>$series->id,'intent'=>$intent,'event_ids'=>$events->pluck('id')->all(),'from_name'=>$fromName,'reply_to'=>$replyTo];
        $plans = $events->mapWithKeys(fn ($event)=>[$event->id=>$this->eventPlan($event,$subject,$body)]);
        if ($plans->sum(fn ($plan)=>count($plan['recipients']))===0) {
            throw ValidationException::withMessages(['audience'=>'No registered player in this series has a valid contact.']);
        }

        return DB::transaction(function () use ($series,$actor,$intent,$events,$plans,$options,$subject,$body) {
            Series::whereKey($series->id)->lockForUpdate()->firstOrFail();
            $batches = collect();
            foreach ($events as $event) {
                $batch = EventCommunicationBatch::firstOrCreate(['source_key'=>'series-compose:'.$series->id.':'.$actor->id.':'.$intent.':'.$event->id], [
                    'event_id'=>$event->id,'created_by'=>$actor->id,'token'=>(string)Str::uuid(),'subject'=>$subject,'body'=>$body,'options'=>$options,...$plans[$event->id],
                ]);
                if ($batch->subject!==$subject || $batch->body!==$body || $this->fingerprint($batch->options)!==$this->fingerprint($options)) {
                    throw ValidationException::withMessages(['intent'=>'This intent already has a saved review. Start a new send intent to change the message or sender.']);
                }
                $batches->push($batch->load('event'));
            }

            return $batches;
        });
    }

    public function approve(Series $series, User $actor, string $intent, bool $acknowledgeMissing): array
    {
        $duplicate = DB::transaction(function () use ($series,$actor,$intent,$acknowledgeMissing) {
            Series::whereKey($series->id)->lockForUpdate()->firstOrFail();
            $events = $this->events($series,$actor);
            $batches = EventCommunicationBatch::where('created_by',$actor->id)->where('options->source','series_compose')
                ->where('options->series_id',$series->id)->where('options->intent',$intent)->orderBy('event_id')->lockForUpdate()->get();
            abort_if($batches->isEmpty(),404);
            abort_unless($batches->pluck('event_id')->all()===$events->pluck('id')->all(),422,'Series events changed. Prepare a new review.');
            if ($batches->every(fn ($batch)=>$batch->approved_at)) {
                return true;
            }
            abort_if($batches->contains(fn ($batch)=>$batch->approved_at),422,'This review has inconsistent approval records. Prepare a fresh review.');
            // Check every event before creating any queue reservation.
            foreach ($batches as $batch) {
                abort_if($batch->created_at->lt(now()->subHour()),422,'This review expired. Prepare a new intent.');
                $plan = $this->eventPlan($events->firstWhere('id',$batch->event_id),$batch->subject,$batch->body);
                if (!hash_equals($batch->fingerprint,$plan['fingerprint'])) {
                    throw ValidationException::withMessages(['preview'=>'Recipients or event details changed. Review a fresh send intent.']);
                }
                if ($batch->issues && !$acknowledgeMissing) {
                    throw ValidationException::withMessages(['acknowledge_missing'=>'Acknowledge the missing contacts in every event preview.']);
                }
            }
            foreach ($batches as $batch) {
                $batch->update(['status'=>'approved','approved_at'=>now()]);
                foreach ($batch->recipients as $recipient) {
                    $this->dispatcher->dispatch('bulk_event_mail',$batch,[$recipient],[
                        'event_id'=>$batch->event_id,'created_by'=>$actor->id,'event_communication_batch_id'=>$batch->id,
                        'campaign_key'=>$intent,'series_id'=>$series->id,'subject'=>$recipient['subject'],'body'=>$recipient['html'],
                        'from_name'=>$batch->options['from_name'],'reply_to'=>$batch->options['reply_to'],'recipient_kind'=>'players','manual_retry_only'=>true,
                    ]);
                }
            }

            return false;
        });
        $batches = $this->batches($series,$actor,$intent);
        $logs = BulkEmailLog::whereIn('payload->event_communication_batch_id',$batches->pluck('id'));
        $counts = ['queued'=>(clone $logs)->where('payload->queue_state','enqueued')->count(),
            'pending'=>(clone $logs)->where('payload->queue_state','prepared')->count(),
            'failed'=>(clone $logs)->where('payload->queue_state','submission_failed')->count(),'skipped'=>(clone $logs)->where('status','skipped')->count()];

        return [...$counts,'duplicate'=>$duplicate,'report_urls'=>$batches->map(fn ($batch)=>route('backend.event-communications.index',['event'=>$batch->event_id,'batch'=>$batch->id,'report_scope'=>'batch']))->all()];
    }
}
