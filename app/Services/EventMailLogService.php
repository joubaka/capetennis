<?php

namespace App\Services;

use App\Models\{BulkEmailLog, Event, EventMailIssue, EventRegion, User};
use App\Services\TeamSelection\RegionManagerAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
class EventMailLogService
{
    public function __construct(private RegionManagerAccessService $access)
    {
    }
    public function fullAccess(Event $event, User $actor): bool
    {
        if ($event->isInterprovincialTrials()) {
            return app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->canManage($event, $actor);
        }
        return $this->access->isEventManager($actor, $event);
    }
    public function regionIds(Event $event, User $actor): array
    {
        return EventRegion::where('event_id', $event->id)->with('events')->get()->filter(fn($region) => $this->access->canManage($actor, $region))->pluck('region_id')->all();
    }
    public function query(Event $event, User $actor): Builder
    {
        $full = $this->fullAccess($event, $actor);
        $regions = $full || $event->isInterprovincialTrials() ? [] : $this->regionIds($event, $actor);
        abort_unless($full || $regions !== [], 403);
        $query = BulkEmailLog::query()->where(function ($q) use ($event) {
            $q->where('payload->event_id', $event->id)->orWhere(function ($legacy) use ($event) {
                $legacy->whereNull('payload->event_id')->where(function ($related) use ($event) {
                    $related->where(fn($r) => $r->where('related_type', Event::class)->where('related_id', $event->id));
                    foreach ([\App\Models\Announcement::class => 'announcements', \App\Models\CategoryEvent::class => 'category_events', \App\Models\TeamSelectionInvitation::class => 'team_selection_invitations', \App\Models\InterprovincialTrialInvitation::class => 'interprovincial_trial_invitations', \App\Models\EventCommunicationBatch::class => 'event_communication_batches'] as $model => $table) {
                        $related->orWhere(fn($r) => $r->where('related_type', $model)->whereIn('related_id', \Illuminate\Support\Facades\DB::table($table)->where('event_id', $event->id)->select('id')));
                    }
                    $related->orWhere(fn($r) => $r->where('related_type', \App\Models\CategoryEventRegistration::class)->whereIn('related_id', \Illuminate\Support\Facades\DB::table('category_event_registrations')->join('category_events', 'category_events.id', '=', 'category_event_registrations.category_event_id')->where('category_events.event_id', $event->id)->select('category_event_registrations.id')));
                    $related->orWhere(fn($r) => $r->where('related_type', \App\Models\Team::class)->whereIn('related_id', \Illuminate\Support\Facades\DB::table('teams')->join('category_events', 'category_events.id', '=', 'teams.category_event_id')->where('category_events.event_id', $event->id)->select('teams.id')));
                    $related->orWhere(fn($r) => $r->where('related_type', \App\Models\MastersInvitation::class)->whereIn('related_id', \Illuminate\Support\Facades\DB::table('masters_invitations')->join('masters_invitation_batches', 'masters_invitation_batches.id', '=', 'masters_invitations.batch_id')->where('masters_invitation_batches.event_id', $event->id)->select('masters_invitations.id')));
                });
            });
        });
        if (!$full) {
            $query->where(function ($q) use ($regions, $event) {
                $q->whereIn('payload->region_id', $regions)->orWhereIn('payload->team_id', \Illuminate\Support\Facades\DB::table('teams')->join('category_events', 'category_events.id', '=', 'teams.category_event_id')->where('category_events.event_id', $event->id)->whereIn('teams.region_id', $regions)->select('teams.id'))->orWhere(fn($r) => $r->where('related_type', \App\Models\TeamSelectionInvitation::class)->whereIn('related_id', \Illuminate\Support\Facades\DB::table('team_selection_invitations')->join('teams', 'teams.id', '=', 'team_selection_invitations.team_id')->where('team_selection_invitations.event_id', $event->id)->whereIn('teams.region_id', $regions)->select('team_selection_invitations.id')));
            });
        }
        return $query;
    }
    /** Durable in-app issues only; never interrupt delivery or send an alert email. */
    public function recordIssue(BulkEmailLog $log): void
    {
        try {
            if (!Schema::hasTable('event_mail_issues')) {
                return;
            }
            $kind = in_array($log->status, ['failed', 'skipped', 'acceptance_unknown'], true) ? $log->status : null;
            if (in_array($log->status, ['queued', 'sending'], true) && $log->updated_at?->lt(now()->subHour())) {
                $kind = 'stalled';
            }
            if (!$kind) {
                return;
            }
            $eventId = data_get($log->payload, 'event_id');
            $senderId = $log->retry_actor_id ?? data_get($log->payload, 'retry_actor_id') ?? data_get($log->payload, 'created_by') ?? data_get($log->payload, 'requested_by_user_id');
            if ($log->related_type === \App\Models\TeamSelectionInvitation::class) {
                $invitation = \App\Models\TeamSelectionInvitation::with('selectionImport')->find($log->related_id);
                $eventId ??= $invitation?->event_id;
                $senderId ??= $invitation?->selectionImport?->prepared_by;
            } elseif ($log->related_type === \App\Models\MastersInvitation::class) {
                $invitation = \App\Models\MastersInvitation::with('batch')->find($log->related_id);
                $eventId ??= $invitation?->batch?->event_id;
                $senderId ??= $invitation?->batch?->created_by;
            } elseif ($log->related_type === \App\Models\InterprovincialTrialInvitation::class) {
                $invitation = \App\Models\InterprovincialTrialInvitation::with('batch')->find($log->related_id);
                $eventId ??= $invitation?->event_id;
                $senderId ??= $invitation?->batch?->reviewed_by_user_id ?? $invitation?->batch?->created_by_user_id;
            }
            if ($log->related_type === \App\Models\EventCommunicationBatch::class) {
                $batch = \App\Models\EventCommunicationBatch::find($log->related_id);
                $eventId ??= $batch?->event_id;
                $senderId ??= $batch?->created_by;
            }
            if ($previewId = data_get($log->payload, 'preview_id')) {
                $preview = \App\Models\TrialMailPreview::find($previewId);
                if ($preview && (int) $preview->event_id === (int) $eventId) {
                    $senderId ??= $preview->actor_id;
                }
            }
            if (!$eventId || !$senderId) {
                return;
            }
            EventMailIssue::firstOrCreate(['log_id' => $log->id, 'attempt_number' => max(1, (int) $log->attempt_number), 'kind' => $kind, 'user_id' => $senderId], ['event_id' => $eventId, 'user_id' => $senderId]);
        } catch (\Throwable) {
            // Reporting must never change transport or retry semantics.
        }
    }
    public static function explanation(BulkEmailLog $log): ?string
    {
        return match ($log->status) {
            'failed' => 'The email could not be sent. Review the message and recipient before retrying.',
            'skipped' => str_starts_with($log->error_message ?? '', 'Duplicate email suppressed') ? 'This send was suppressed as a duplicate.' : 'This recipient was excluded from sending. Review the audience and contact details.',
            'acceptance_unknown' => 'Mail-server acceptance is uncertain. Investigate before sending again.',
            default => in_array($log->status, ['queued', 'sending'], true) && $log->updated_at?->lt(now()->subHour()) ? 'No progress for over an hour. Check the queue worker; do not resend blindly.' : null,
        };
    }
    public static function subject(BulkEmailLog $log): string
    {
        return (string) (data_get($log->payload, 'rendered_subject') ?? data_get($log->payload, 'subject') ?? data_get($log->payload, 'title') ?? 'Email #' . $log->id);
    }
    public static function canRetry(BulkEmailLog $log): bool
    {
        // Existing reviewed invitations/rankings have their own fresh-contact and
        // snapshot checks. Their retries must continue through that workflow.
        if (data_get($log->payload, 'preview_id')) {
            return false;
        }
        if ($batchId = data_get($log->payload, 'event_communication_batch_id')) {
            $batch = \App\Models\EventCommunicationBatch::find($batchId);
            if (!$batch || data_get($batch->options, 'source') !== 'email_log_retry') {
                return false;
            }
        }
        return $log->status === 'failed' && !$log->sent_at && !$log->accepted_at && in_array($log->mail_type, ['generic_bulk_email', 'bulk_event_mail', 'event_email', 'team_email', 'region_email', 'category_email', 'nomination_email', 'unregistered_event_email', 'unregistered_region_email', 'unregistered_team_email'], true) && (bool) (data_get($log->payload, 'message') ?? data_get($log->payload, 'body'));
    }
    public function sender(BulkEmailLog $log): ?User
    {
        $id = data_get($log->payload, 'created_by');
        if (!$id && $batchId = data_get($log->payload, 'event_communication_batch_id')) {
            $id = \App\Models\EventCommunicationBatch::whereKey($batchId)->value('created_by');
        }
        if (!$id && $previewId = data_get($log->payload, 'preview_id')) {
            $id = \App\Models\TrialMailPreview::whereKey($previewId)->value('actor_id');
        }
        return $id ? User::find($id) : null;
    }
}
