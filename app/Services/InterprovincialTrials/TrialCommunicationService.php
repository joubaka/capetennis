<?php
namespace App\Services\InterprovincialTrials;

use App\Models\{Event, User, EventNomination, InterprovincialTrialInvitation, TrialSquadDraft, TrialMailPreview, TrialMailSchedule, TrialMessageTemplate, BulkEmailLog};
use App\Services\BulkMailDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonInterface;

class TrialCommunicationService
{
    public function __construct(private TrialProgrammeService $programmes, private BulkMailDispatcher $dispatcher) {}

    private function content(string $subject, string $body): void
    {
        if (trim($subject) === '' || strlen($subject) > 255 || strpbrk($subject, "\r\n") !== false || trim($body) === '' || strlen($body) > 50000) {
            throw ValidationException::withMessages(['message' => 'Enter a subject up to 255 characters and message up to 50000 characters.']);
        }
    }

    public function preview(Event $event, User $actor, array $options, string $subject, string $body): TrialMailPreview
    {
        $this->programmes->authorize($event, $actor);
        $this->content($subject, $body);
        [$recipients, $excluded] = $this->resolve($event, $options, $subject, $body);
        return TrialMailPreview::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'token' => (string) Str::uuid(),
            'options' => $options, 'subject' => $subject, 'body' => $body, 'recipients' => $recipients, 'excluded' => $excluded,
            'snapshot_hash' => hash('sha256', json_encode([$recipients, $excluded])), 'expires_at' => now()->addMinutes(15)]);
    }

    public function commit(TrialMailPreview $preview, User $actor): array
    {
        return DB::transaction(function () use ($preview, $actor) {
            $locked = TrialMailPreview::lockForUpdate()->findOrFail($preview->id);
            $event = Event::findOrFail($locked->event_id);
            $this->programmes->authorize($event, $actor);
            abort_unless((int) $locked->actor_id === (int) $actor->id, 403);
            if ($locked->committed_at) return ['queued' => 0];
            abort_if($locked->expires_at->isPast(), 422, 'Preview expired. Review a new preview.');
            [$current, $excluded] = $this->resolve($event, $locked->options, $locked->subject, $locked->body);
            if (! hash_equals($locked->snapshot_hash, hash('sha256', json_encode([$current, $excluded])))) {
                throw ValidationException::withMessages(['preview' => 'Contacts or registration statuses changed. Review a new preview.']);
            }
            $queued = 0;
            foreach ($locked->recipients as $recipient) {
                $stats = $this->dispatcher->dispatch('trial_communication', $locked, [['email' => $recipient['email'], 'name' => $recipient['name']]],
                    ['subject' => $recipient['subject'], 'body' => nl2br(e($recipient['body'])), 'manual_retry_only' => true,
                        'event_id' => $event->id, 'preview_id' => $locked->id, 'actor_id' => $actor->id], true);
                $queued += $stats['queued'];
            }
            $locked->update(['committed_at' => now()]);
            activity('interprovincial-trials')->performedOn($locked)->causedBy($actor)->withProperties(['recipient_count' => count($locked->recipients), 'snapshot_hash' => $locked->snapshot_hash])->log('Reviewed Trials messages queued');
            return ['queued' => $queued];
        });
    }

    public function saveTemplate(Event $event, User $actor, string $name, string $subject, string $body): TrialMessageTemplate
    {
        $this->programmes->authorize($event, $actor); $this->content($subject, $body);
        abort_unless(trim($name) !== '' && strlen($name) <= 255, 422);
        return TrialMessageTemplate::create(['event_id' => $event->id, 'created_by' => $actor->id, 'name' => $name, 'subject' => $subject, 'body' => $body]);
    }

    public function templatesFor(Event $event, User $actor): \Illuminate\Support\Collection
    {
        $this->programmes->authorize($event, $actor);
        $region = \App\Models\TrialProgramme::where('event_id', $event->id)->value('region_id')
            ?? DB::table('event_regions')->where('event_id', $event->id)->value('region_id');
        $events = $region ? DB::table('event_regions')->where('region_id', $region)->pluck('event_id')
            ->merge(\App\Models\TrialProgramme::where('region_id', $region)->pluck('event_id'))->push($event->id)->unique() : collect([$event->id]);
        $authorized = Event::whereIn('id', $events)->get()->filter(fn ($candidate) => $candidate->isInterprovincialTrials() && $this->programmes->canManage($candidate, $actor))->pluck('id');
        return TrialMessageTemplate::whereIn('event_id', $authorized)->latest()->limit(100)->get();
    }

    public function schedule(TrialMailPreview $preview, User $actor, CarbonInterface $start, ?int $repeatHours, ?CarbonInterface $stop): TrialMailSchedule
    {
        return DB::transaction(function () use ($preview, $actor, $start, $repeatHours, $stop) {
            $preview = TrialMailPreview::lockForUpdate()->findOrFail($preview->id);
            $event = Event::findOrFail($preview->event_id); $this->programmes->authorize($event, $actor);
            abort_unless((int) $preview->actor_id === (int) $actor->id && ! $preview->committed_at && $preview->expires_at->isFuture(), 422);
            abort_unless($start->isFuture() && ($repeatHours === null || ($repeatHours >= 1 && $repeatHours <= 8760)) && (! $stop || $stop->gte($start)), 422);
            [$current, $excluded] = $this->resolve($event, $preview->options, $preview->subject, $preview->body);
            abort_unless(hash_equals($preview->snapshot_hash, hash('sha256', json_encode([$current, $excluded]))), 422);
            $attributes = ['event_id' => $event->id, 'actor_id' => $actor->id, 'options' => $preview->options,
                'subject' => $preview->subject, 'body' => $preview->body, 'next_send_at' => $start, 'repeat_hours' => $repeatHours, 'stop_at' => $stop, 'active' => true];
            if (! empty($preview->options['_schedule_id'])) {
                $schedule = TrialMailSchedule::where('event_id', $event->id)->lockForUpdate()->findOrFail($preview->options['_schedule_id']);
                $schedule->update($attributes);
            } else {
                $schedule = TrialMailSchedule::create($attributes);
            }
            $preview->update(['committed_at' => now()]);
            activity('interprovincial-trials')->performedOn($schedule)->causedBy($actor)->log('Trials reminder schedule approved');
            return $schedule;
        });
    }

    public function pause(TrialMailSchedule $schedule, User $actor): void
    {
        $this->programmes->authorize(Event::findOrFail($schedule->event_id), $actor);
        $schedule->update(['active' => false]);
        activity('interprovincial-trials')->performedOn($schedule)->causedBy($actor)->log('Trials reminder schedule paused');
    }

    public function runDue(): int
    {
        $processed = 0;
        TrialMailSchedule::where('active', true)->where('next_send_at', '<=', now())->orderBy('id')->chunkById(50, function ($schedules) use (&$processed) {
            foreach ($schedules as $schedule) {
                DB::transaction(function () use ($schedule, &$processed) {
                    $locked = TrialMailSchedule::lockForUpdate()->findOrFail($schedule->id);
                    if (! $locked->active || $locked->next_send_at->isFuture()) return;
                    $event = Event::find($locked->event_id); $actor = User::find($locked->actor_id);
                    if (! $event || ! $actor || ! $this->programmes->canManage($event, $actor) || ($locked->stop_at && $locked->stop_at->isPast())) {
                        $locked->update(['active' => false]); return;
                    }
                    $preview = $this->preview($event, $actor, $locked->options, $locked->subject, $locked->body);
                    $this->commit($preview, $actor);
                    $next = $locked->repeat_hours ? now()->addHours($locked->repeat_hours) : null;
                    $locked->update(['next_send_at' => $next ?? $locked->next_send_at, 'active' => $next !== null && (! $locked->stop_at || $next->lte($locked->stop_at))]);
                    $processed++;
                });
            }
        });
        return $processed;
    }

    public function retry(BulkEmailLog $log, User $actor): bool
    {
        abort_unless($log->mail_type === 'trial_communication', 404);
        $this->programmes->authorize(Event::findOrFail(data_get($log->payload, 'event_id')), $actor);
        $claimed = BulkEmailLog::whereKey($log->id)->where('status', 'failed')->whereNull('sent_at')->update(['status' => 'queued', 'failed_at' => null, 'error_message' => null]);
        if ($claimed) {
            \App\Jobs\SendBulkEmailJob::dispatch($log->id, true)->afterCommit();
            activity('interprovincial-trials')->performedOn($log)->causedBy($actor)->log('Failed Trials message manually retried');
        }
        return (bool) $claimed;
    }

    private function resolve(Event $event, array $options, string $subject, string $body): array
    {
        $audience = $options['audience'] ?? 'nominations'; $filter = $options['filter'] ?? 'all';
        abort_unless(in_array($audience, ['nominations', 'teams'], true) && in_array($filter, ['all', 'not_registered', 'payment_pending', 'paid', 'declined', 'confirmed'], true), 422);
        $rows = []; $excluded = [];
        if ($audience === 'nominations') {
            $items = EventNomination::with(['player.user', 'player.users', 'categoryEvent'])->where('event_id', $event->id)
                ->when(! empty($options['category_ids']), fn ($q) => $q->whereIn('category_event_id', $options['category_ids']))
                ->when(! empty($options['individual_ids']), fn ($q) => $q->whereIn('id', $options['individual_ids']))->orderBy('id')->get();
            foreach ($items as $item) {
                if (! $item->categoryEvent?->nominations_published) continue;
                $lifecycle = InterprovincialTrialInvitation::where('event_id', $event->id)->where('nomination_id', $item->id)->where('status', '!=', 'prepared')->latest('id')->first();
                $status = $lifecycle?->status ?? 'not_registered';
                if (! $this->matches($filter, $status)) continue;
                $rows[] = ['id' => $item->id, 'player' => $item->player, 'name' => $item->player?->full_name ?? trim($item->nominee_name.' '.$item->nominee_surname), 'fallback' => $item->nominee_email, 'status' => $status,
                    'url' => route('events.show', $event->id).'?nomination='.$item->id];
            }
        } else {
            $draft = TrialSquadDraft::where('event_id', $event->id)->where('status','finalised')->whereNotNull('finalised_at')->latest('id')->first();
            if ($draft) foreach ($draft->slots()->with(['player.user', 'player.users'])->whereNotNull('player_id')
                ->when(! empty($options['category_ids']), fn ($q) => $q->whereIn('category_event_id', $options['category_ids']))
                ->when(! empty($options['individual_ids']), fn ($q) => $q->whereIn('id', $options['individual_ids']))
                ->when(! empty($options['tiers']), fn ($q) => $q->whereIn('tier', $options['tiers']))->orderBy('id')->get() as $slot) {
                $status = $slot->response;
                $participation = \App\Models\TrialParticipation::with('order')->where('event_id', $event->id)->where('player_id', $slot->player_id)->first();
                if ($participation?->isPaid()) $status = 'paid';
                if (! $this->matches($filter, $status)) continue;
                $rows[] = ['id' => $slot->id, 'player' => $slot->player, 'name' => $slot->player->full_name.' ('.$slot->tier.')', 'fallback' => null, 'status' => $status, 'url' => route('events.show', $event->id).'?slot='.$slot->id.'#trial-squad-slot-'.$slot->id];
            }
        }
        $groups = [];
        foreach ($rows as $row) {
            $player = $row['player'];
            $contacts = collect([$player?->email, $player?->user?->email, $row['fallback']])->merge($player?->users?->pluck('email') ?? []);
            $valid = [];
            foreach ($contacts->filter()->unique() as $email) {
                $email = strtolower(trim($email));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) { $excluded[] = ['player' => $row['name'], 'reason' => 'Invalid contact email']; continue; }
                $valid[$email] = true;
            }
            if (! $valid) $excluded[] = ['player' => $row['name'], 'reason' => 'No valid email'];
            foreach (array_keys($valid) as $email) $groups[$email][] = ['id' => $row['id'], 'name' => $row['name'], 'status' => $row['status'], 'url' => $row['url']];
        }
        ksort($groups); $recipients = [];
        foreach ($groups as $email => $players) {
            $variables = ['{event}' => $event->name, '{players}' => collect($players)->map(fn ($p) => $p['name'].' — '.$p['url'])->implode("\n"),
                '{fee}' => number_format((float) ($audience === 'teams' ? \App\Models\TrialProgramme::where('event_id', $event->id)->value('participation_fee') : $event->entryFee), 2),
                '{deadline}' => $audience === 'teams' ? (string) \App\Models\TrialProgramme::where('event_id', $event->id)->value('response_deadline') : (string) $event->registrationClosesAt()?->format('Y-m-d'), '{url}' => route('events.show', $event->id)];
            $renderedSubject = preg_replace('/[\r\n]+/', ' ', strtr($subject, $variables));
            $recipients[] = ['email' => $email, 'name' => collect($players)->pluck('name')->implode(', '), 'players' => $players, 'subject' => mb_substr($renderedSubject, 0, 255), 'body' => strtr($body, $variables)];
        }
        return [$recipients, $excluded];
    }

    private function matches(string $filter, string $status): bool
    {
        return match ($filter) { 'all' => true, 'not_registered' => in_array($status, ['not_registered','sent','queued','failed','pending'],true),
            'payment_pending' => in_array($status,['accepted_pending_payment','confirmed'],true), 'paid' => in_array($status,['paid_confirmed','paid'],true),
            'declined' => $status === 'declined', 'confirmed' => in_array($status,['confirmed','paid','paid_confirmed'],true) };
    }
}
