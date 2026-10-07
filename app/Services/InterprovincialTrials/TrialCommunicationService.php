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
        $options = array_merge($options, \App\Services\CommunicationSender::resolve($options, $actor));
        [$recipients, $excluded] = $this->resolve($event, $options, $subject, $body);
        return TrialMailPreview::create(['event_id' => $event->id, 'actor_id' => $actor->id, 'token' => (string) Str::uuid(),
            'options' => $options, 'subject' => $subject, 'body' => $body, 'recipients' => $recipients, 'excluded' => $excluded,
            'snapshot_hash' => hash('sha256', json_encode([$recipients, $excluded, $options['from_name'], $options['reply_to']])), 'expires_at' => now()->addMinutes(15)]);
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
            if (! hash_equals($locked->snapshot_hash, hash('sha256', json_encode(isset($locked->options['from_name']) ? [$current, $excluded, $locked->options['from_name'], $locked->options['reply_to']] : [$current, $excluded])))) {
                throw ValidationException::withMessages(['preview' => 'Contacts or registration statuses changed. Review a new preview.']);
            }
            $queued = 0;
            foreach ($locked->recipients as $recipient) {
                $stats = $this->dispatcher->dispatch('trial_communication', $locked, [['email' => $recipient['email'], 'name' => $recipient['name']]],
                    ['subject' => $recipient['subject'], 'body' => nl2br(e($recipient['body'])), 'manual_retry_only' => true,
                        'from_name' => $locked->options['from_name'] ?? $actor->name, 'reply_to' => $locked->options['reply_to'] ?? $actor->email,
                        'event_id' => $event->id, 'preview_id' => $locked->id, 'actor_id' => $actor->id, 'created_by' => $actor->id], true);
                $queued += $stats['queued'];
            }
            $locked->update(['committed_at' => now()]);
            if (! empty($locked->options['_schedule_id'])) {
                $schedule = TrialMailSchedule::where('event_id', $event->id)->lockForUpdate()->findOrFail($locked->options['_schedule_id']);
                $next = $schedule->repeat_hours ? now()->addHours($schedule->repeat_hours) : null;
                $schedule->update(['next_send_at' => $next ?? $schedule->next_send_at, 'active' => $next !== null && (! $schedule->stop_at || $next->lte($schedule->stop_at))]);
            }
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
            abort_unless(hash_equals($preview->snapshot_hash, hash('sha256', json_encode(isset($preview->options['from_name']) ? [$current, $excluded, $preview->options['from_name'], $preview->options['reply_to']] : [$current, $excluded]))), 422);
            $attributes = ['event_id' => $event->id, 'actor_id' => $actor->id, 'options' => $preview->options,
                'subject' => $preview->subject, 'body' => $preview->body, 'next_send_at' => $start, 'repeat_hours' => $repeatHours, 'stop_at' => $stop, 'active' => true];
            if (! empty($preview->options['_schedule_id'])) {
                $schedule = TrialMailSchedule::where('event_id', $event->id)->lockForUpdate()->findOrFail($preview->options['_schedule_id']);
                $schedule->update($attributes);
            } else {
                $schedule = TrialMailSchedule::create($attributes);
            }
            $preview->update(['committed_at' => now()]);
            activity('interprovincial-trials')->performedOn($schedule)->causedBy($actor)->log('Trials reminder saved for manual review');
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
                    // A due schedule is a reminder to review, never approval to send.
                    // Keep it due until an actor previews and explicitly approves this occurrence.
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
        $claimed = DB::transaction(function () use ($log, $actor): bool {
            $current = BulkEmailLog::whereKey($log->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'failed' || $current->sent_at || $current->accepted_at) return false;
            $current->update(['status' => 'queued', 'retry_actor_id' => $actor->id, 'queued_at' => now(), 'failed_at' => null, 'error_message' => null,
                'payload' => [...$current->payload, 'retry_actor_id' => $actor->id]]);
            \App\Jobs\SendBulkEmailJob::dispatch($current->id, true)->afterCommit();
            activity('interprovincial-trials')->performedOn($current)->causedBy($actor)->log('Failed Trials message manually retried');
            return true;
        });
        return (bool) $claimed;
    }

    private function resolve(Event $event, array $options, string $subject, string $body): array
    {
        $audience = $options['audience'] ?? 'nominations'; $filter = $options['filter'] ?? 'all';
        abort_unless(in_array($audience, ['all', 'nominations', 'teams'], true) && in_array($filter, ['all', 'not_registered', 'payment_pending', 'paid', 'declined', 'confirmed'], true), 422);
        if (! empty($options['region_id'])) {
            abort_unless(DB::table('event_regions')->where('event_id', $event->id)->where('region_id', $options['region_id'])->exists(), 404);
            $programmeRegion = \App\Models\TrialProgramme::where('event_id', $event->id)->value('region_id');
            if ($programmeRegion && (int) $programmeRegion !== (int) $options['region_id']) return [[], []];
        }
        $rows = []; $excluded = [];
        $restrictIndividuals = $audience === 'all' && (! empty($options['nominee_ids']) || ! empty($options['slot_ids']));
        if (in_array($audience, ['nominations', 'all'], true)) {
            $individualIds = $audience === 'all' ? ($options['nominee_ids'] ?? []) : ($options['individual_ids'] ?? []);
            $items = EventNomination::with(['player.user', 'player.users', 'categoryEvent'])->where('event_id', $event->id)
                ->when($restrictIndividuals && empty($individualIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->when(! empty($options['category_ids']), fn ($q) => $q->whereIn('category_event_id', $options['category_ids']))
                ->when(! empty($individualIds), fn ($q) => $q->whereIn('id', $individualIds))->orderBy('id')->get();
            $lifecycles = InterprovincialTrialInvitation::where('event_id', $event->id)
                ->whereIn('nomination_id', $items->pluck('id'))
                ->where('status', '!=', 'prepared')
                ->orderByDesc('id')->get()->unique('nomination_id')->keyBy('nomination_id');
            foreach ($items as $item) {
                $lifecycle = $lifecycles->get($item->id);
                $status = $lifecycle?->status ?? 'not_registered';
                if (! $this->matches($filter, $status)) continue;
                $rows[] = ['id' => $item->id, 'player' => $item->player, 'name' => $item->player?->full_name ?? trim($item->nominee_name.' '.$item->nominee_surname), 'fallback' => $item->nominee_email, 'status' => $status,
                    'url' => route('events.show', $event->id).'?nomination='.$item->id];
            }
        }
        if (in_array($audience, ['teams', 'all'], true)) {
            $individualIds = $audience === 'all' ? ($options['slot_ids'] ?? []) : ($options['individual_ids'] ?? []);
            $draft = TrialSquadDraft::where('event_id', $event->id)->latest('id')->first();
            $slots = $draft?->slots()->with(['player.user', 'player.users', 'categoryEvent.category'])->whereNotNull('player_id')
                ->when($restrictIndividuals && empty($individualIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->when(! empty($options['category_ids']), fn ($q) => $q->whereIn('category_event_id', $options['category_ids']))
                ->when(! empty($individualIds), fn ($q) => $q->whereIn('id', $individualIds))
                ->when(! empty($options['tiers']), fn ($q) => $q->whereIn('tier', $options['tiers']))->orderBy('id')->get() ?? collect();
            $participations = \App\Models\TrialParticipation::with('order')->where('event_id', $event->id)
                ->whereIn('player_id', $slots->pluck('player_id'))->get()->keyBy('player_id');
            foreach ($slots as $slot) {
                $status = $slot->response;
                $participation = $participations->get($slot->player_id);
                if ($participation?->isPaid()) $status = 'paid';
                if (! $this->matches($filter, $status)) continue;
                $rows[] = ['id' => $slot->id, 'player' => $slot->player, 'name' => $slot->player->full_name.' ('.$slot->categoryEvent?->category?->name.' — '.$slot->tier.')', 'fallback' => null, 'status' => $status, 'url' => route('events.show', $event->id).'?slot='.$slot->id.'#trial-squad-slot-'.$slot->id];
            }
        }
        $programme = $audience === 'teams' ? \App\Models\TrialProgramme::where('event_id', $event->id)->first() : null;
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
        $recipientMode = $options['recipients'] ?? 'players';
        abort_unless(in_array($recipientMode, ['players', 'managers', 'both'], true), 422);
        if ($recipientMode !== 'players') {
            $regionId = \App\Models\TrialProgramme::where('event_id', $event->id)->value('region_id');
            $eventRegions = DB::table('event_regions')->where('event_id', $event->id)->pluck('region_id')->unique();
            $regionId ??= $eventRegions->count() === 1 ? $eventRegions->sole() : null;
            $managerEmails = DB::table('event_region_managers')
                ->join('event_regions', 'event_regions.id', '=', 'event_region_managers.event_region_id')
                ->join('users', 'users.id', '=', 'event_region_managers.user_id')
                ->where('event_regions.event_id', $event->id)
                ->where('event_regions.region_id', $regionId ?? -1)
                ->pluck('users.email')->unique();
            if ($managerEmails->isEmpty()) $excluded[] = ['player' => 'Regional manager', 'reason' => 'No manager assigned to this programme region'];
            $summary = collect($rows)->map(fn ($row) => ['id' => $row['id'], 'name' => $row['name'], 'status' => $row['status'], 'url' => $row['url']])->all();
            if ($recipientMode === 'managers') $groups = [];
            foreach ($managerEmails as $email) {
                $email = strtolower(trim((string) $email));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) { $excluded[] = ['player' => 'Regional manager', 'reason' => 'No valid email']; continue; }
                if ($summary) $groups[$email] = $summary;
            }
        }
        ksort($groups); $recipients = [];
        foreach ($groups as $email => $players) {
            $variables = ['{event}' => $event->name, '{players}' => collect($players)->map(fn ($p) => $p['name'].' — '.$p['status'].' — '.$p['url'])->implode("\n"),
                '{fee}' => number_format((float) ($audience === 'teams' ? $programme?->participation_fee : $event->entryFee), 2),
                '{deadline}' => $audience === 'teams' ? (string) $programme?->response_deadline : (string) $event->registrationClosesAt()?->format('Y-m-d'), '{url}' => route('events.show', $event->id)];
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
