<?php

namespace App\Services\InterprovincialTrials;

use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\Event;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function prepare(Event $event, User $actor): InterprovincialTrialInvitationBatch
    {
        return DB::transaction(function () use ($event, $actor): InterprovincialTrialInvitationBatch {
            $batch = InterprovincialTrialInvitationBatch::query()->lockForUpdate()
                ->where('event_id', $event->id)->where('status', InterprovincialTrialInvitationBatch::DRAFT)->latest('id')->first();
            $nominations = $event->nominations()->lockForUpdate()->with(['player.user', 'player.users', 'categoryEvent.category'])->orderBy('id')->get();
            $rows = $this->snapshotRows($event, $nominations);
            $hash = $this->snapshotHash($rows);
            $batch ??= InterprovincialTrialInvitationBatch::create([
                'event_id' => $event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
                'created_by_user_id' => $actor->id, 'snapshot_hash' => $hash,
            ]);
            if (! hash_equals($batch->snapshot_hash, $hash)) {
                $batch->update(['snapshot_hash' => $hash, 'snapshot_version' => $batch->snapshot_version + 1,
                    'reviewed_by_user_id' => null, 'reviewed_at' => null]);
            }
            $nominationIds = $nominations->pluck('id');
            $batch->invitations()->whereNotIn('nomination_id', $nominationIds)->delete();

            foreach ($rows as $row) {
                InterprovincialTrialInvitation::updateOrCreate(
                    ['batch_id' => $batch->id, 'nomination_id' => $row['nomination_id']],
                    ['event_id' => $event->id, 'category_event_id' => $row['category_event_id'],
                        'player_id' => $row['player_id'], 'recipient_email' => $row['recipient_email'],
                        'recipient_name' => $row['recipient_name'], 'status' => 'prepared']
                );
            }

            return $batch->fresh(['event', 'invitations.player', 'invitations.categoryEvent.category']);
        });
    }

    public function review(InterprovincialTrialInvitationBatch $batch, User $actor, string $expectedHash): InterprovincialTrialInvitationBatch
    {
        return DB::transaction(function () use ($batch, $actor, $expectedHash): InterprovincialTrialInvitationBatch {
            $locked = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! hash_equals($locked->snapshot_hash, $expectedHash)) {
                throw ValidationException::withMessages(['batch' => 'The invitation recipients changed. Prepare and review the list again.']);
            }
            if ($locked->status === InterprovincialTrialInvitationBatch::REVIEWED) return $locked;
            if ($locked->status !== InterprovincialTrialInvitationBatch::DRAFT) throw ValidationException::withMessages(['batch' => 'Only a draft invitation batch can be reviewed.']);
            $currentRows = $this->snapshotRows($locked->event, $locked->event->nominations()->lockForUpdate()->with(['player.user', 'player.users', 'categoryEvent.category'])->orderBy('id')->get());
            if (! hash_equals($locked->snapshot_hash, $this->snapshotHash($currentRows))) {
                throw ValidationException::withMessages(['batch' => 'Nominations or recipients changed. Prepare and review the list again.']);
            }
            if (! $locked->invitations()->exists()) throw ValidationException::withMessages(['batch' => 'Nominate at least one player before reviewing invitations.']);
            if ($locked->invitations()->whereNull('recipient_email')->exists()) throw ValidationException::withMessages(['batch' => 'Every nominated player needs a directly assigned or linked account email.']);
            $locked->update(['status' => InterprovincialTrialInvitationBatch::REVIEWED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()]);
            return $locked->fresh();
        });
    }

    public function queue(InterprovincialTrialInvitationBatch $batch): InterprovincialTrialInvitationBatch
    {
        return DB::transaction(function () use ($batch): InterprovincialTrialInvitationBatch {
            $locked = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === InterprovincialTrialInvitationBatch::QUEUED) return $locked;
            if ($locked->status !== InterprovincialTrialInvitationBatch::REVIEWED) {
                throw ValidationException::withMessages(['batch' => 'Review the exact recipient list before sending invitations.']);
            }
            $currentRows = $this->snapshotRows(
                $locked->event,
                $locked->event->nominations()->lockForUpdate()
                    ->with(['player.user', 'player.users', 'categoryEvent.category'])
                    ->orderBy('id')->get()
            );
            if (! hash_equals($locked->snapshot_hash, $this->snapshotHash($currentRows))) {
                throw ValidationException::withMessages(['batch' => 'Nominations or recipients changed. Prepare and review the list again.']);
            }
            foreach ($locked->invitations()->whereNotNull('recipient_email')->get() as $invitation) {
                $claimed = DB::table('interprovincial_trial_mail_dispatches')->insertOrIgnore(['invitation_id' => $invitation->id, 'created_at' => now(), 'updated_at' => now()]);
                if ($claimed) {
                    $log = BulkEmailLog::create(['mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class,
                        'related_id' => $invitation->id, 'recipient_email' => $invitation->recipient_email,
                        'recipient_name' => $invitation->recipient_name, 'status' => 'queued',
                        'payload' => ['event_id' => $locked->event_id, 'invitation_id' => $invitation->id], 'queued_at' => now()]);
                    DB::table('interprovincial_trial_mail_dispatches')->where('invitation_id', $invitation->id)->update(['bulk_email_log_id' => $log->id, 'updated_at' => now()]);
                    DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $locked->event_id));
                }
                $invitation->update(['status' => 'queued', 'queued_at' => $invitation->queued_at ?? now()]);
            }
            $locked->update(['status' => InterprovincialTrialInvitationBatch::QUEUED, 'queued_at' => now()]);
            return $locked->fresh();
        });
    }

    public function retryFailed(InterprovincialTrialInvitationBatch $batch, InterprovincialTrialInvitation $invitation): bool
    {
        return DB::transaction(function () use ($batch, $invitation): bool {
            $lockedBatch = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $locked = InterprovincialTrialInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless((int) $locked->batch_id === (int) $lockedBatch->id
                && (int) $locked->event_id === (int) $lockedBatch->event_id, 404);

            if (in_array($locked->status, ['queued', 'sending', 'sent'], true)) return false;
            if ($locked->status !== 'failed') {
                throw ValidationException::withMessages(['invitation' => 'Only a failed invitation email can be retried.']);
            }

            $dispatch = DB::table('interprovincial_trial_mail_dispatches')->where('invitation_id', $locked->id)->lockForUpdate()->first();
            $log = $dispatch?->bulk_email_log_id ? BulkEmailLog::query()->lockForUpdate()->find($dispatch->bulk_email_log_id) : null;
            if (! $log || $log->mail_type !== 'interprovincial_trial_invitation'
                || $log->related_type !== InterprovincialTrialInvitation::class
                || (int) $log->related_id !== (int) $locked->id
                || $log->status !== 'failed' || $log->sent_at) {
                throw ValidationException::withMessages(['invitation' => 'The failed invitation does not have a matching failed email log.']);
            }

            $locked->update(['status' => 'queued', 'queued_at' => now()]);
            $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'skipped_at' => null, 'error_message' => null]);
            DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $lockedBatch->event_id));

            return true;
        });
    }

    private function snapshotRows(Event $event, $nominations): array
    {
        return $nominations->map(function ($nomination) use ($event): array {
            abort_unless((int) $nomination->categoryEvent?->event_id === (int) $event->id, 422);
            $player = $nomination->player;
            $direct = $player?->user && filled($player->user->email) ? $player->user : null;
            $recipient = $direct ?: $player?->users?->filter(fn ($user) => filled($user->email))->sortBy('id')->first();
            return ['nomination_id' => (int) $nomination->id, 'category_event_id' => (int) $nomination->category_event_id,
                'player_id' => (int) $nomination->player_id, 'recipient_email' => $recipient?->email,
                'recipient_user_id' => $recipient?->id, 'recipient_name' => trim(($player?->name ?? '').' '.($player?->surname ?? ''))];
        })->sortBy('nomination_id')->values()->all();
    }

    private function snapshotHash(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }
}
