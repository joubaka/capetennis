<?php

namespace App\Services;

use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, Event, Player, SiteSetting, Team, TeamPaymentOrder, TrialParticipation, User};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/** A completed roster removal, never opening a refund chooser, authorizes this notice. */
final class TeamWithdrawalNotificationService
{
    public function completed(Team $team, Player $player, Event $event, User $actor, ?TeamPaymentOrder $order = null): void
    {
        if (DB::transactionLevel() === 0) throw new \LogicException('Record the withdrawal notice inside the roster transaction.');
        $activity = activity('withdrawal')->performedOn($team)->causedBy($actor)
            ->withProperties(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id,
                'order_id' => $order?->id, 'withdrawn_at' => now()->toIso8601String()])
            ->log('team participant withdrawal completed');
        DB::afterCommit(function () use ($activity): void {
            try {
                $this->record($activity);
            } catch (\Throwable) {
                \Illuminate\Support\Facades\Log::error('Withdrawal notification preparation failed.', ['activity_id' => $activity->id]);
            }
        });
    }

    public function forOrder(TeamPaymentOrder $order): void
    {
        $activities = Activity::where('log_name', 'withdrawal')->where('description', 'team participant withdrawal completed')
            ->where('properties->order_id', $order->id)->get();
        foreach ($activities as $activity) $this->record($activity);
    }

    public function participationCompleted(TrialParticipation $participation, User $actor): void
    {
        if (DB::transactionLevel() === 0) throw new \LogicException('Record the withdrawal notice inside the participation transaction.');
        $activity = activity('withdrawal')->performedOn($participation)->causedBy($actor)
            ->withProperties(['event_id' => $participation->event_id, 'player_id' => $participation->player_id,
                'order_id' => $participation->order_id, 'withdrawn_at' => now()->toIso8601String()])
            ->log('team participant withdrawal completed');
        DB::afterCommit(function () use ($activity): void {
            try { $this->record($activity); }
            catch (\Throwable) { \Illuminate\Support\Facades\Log::error('Withdrawal notification preparation failed.', ['activity_id' => $activity->id]); }
        });
    }

    private function messages(Activity $activity): array
    {
        if ($activity->log_name !== 'withdrawal' || $activity->description !== 'team participant withdrawal completed'
            || ! in_array($activity->subject_type, [Team::class, TrialParticipation::class], true) || $activity->causer_type !== User::class
            || blank($activity->properties['withdrawn_at'] ?? null)) return [];
        $event = Event::find($activity->properties['event_id'] ?? 0);
        $participation = $activity->subject_type === TrialParticipation::class ? TrialParticipation::find($activity->subject_id) : null;
        $team = $activity->subject_type === Team::class ? Team::find($activity->subject_id) : null;
        $player = Player::find($activity->properties['player_id'] ?? 0);
        $actor = User::find($activity->causer_id);
        if (! $event || ! $player || ! $actor) return [];
        if ($activity->subject_type === Team::class && (! $team || (int) ($activity->properties['team_id'] ?? 0) !== (int) $team->id
            || ! app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->teamBelongsToEvent($team, $event))) return [];
        if ($activity->subject_type === TrialParticipation::class && (! $participation || (int) $participation->event_id !== (int) $event->id
            || (int) $participation->player_id !== (int) $player->id || (int) $participation->order_id !== (int) ($activity->properties['order_id'] ?? 0))) return [];
        $order = isset($activity->properties['order_id']) ? TeamPaymentOrder::find($activity->properties['order_id']) : null;
        if (isset($activity->properties['order_id']) && (! $order || (int) $order->event_id !== (int) $event->id
            || ($team && (int) $order->team_id !== (int) $team->id) || $order->effective_player_id !== (int) $player->id || ! $order->withdrawn_at)) return [];
        $recipients = collect();
        if (SiteSetting::emailEnabled('player_email_on_team_withdrawal')) {
            if ($order?->user?->email) $recipients->push($order->user->email);
            $recipients = $recipients->merge(app(TeamSelectionContactService::class)->emails($player));
        }
        if (SiteSetting::emailEnabled('email_on_team_withdrawal')) {
            $recipients = $recipients->merge(User::role('super-user')->pluck('email'))->merge($event->admins->pluck('email'));
        }
        $at = \Illuminate\Support\Carbon::parse($activity->properties['withdrawn_at']);
        $body = '<p><strong>Player withdrawn from tournament.</strong></p><p>Event: '.e($event->name).($team ? '<br>Team: '.e($team->name) : '').'</p>'
            .WithdrawalNoticeFacts::html($player->full_name, $at, $actor->id)
            .'<p>The player’s participation has been withdrawn. Any eligible refund is handled separately.</p>';
        return $recipients->map(fn ($email) => mb_strtolower(trim((string) $email)))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()
            ->map(fn ($email) => ['email' => $email, 'event_id' => $event->id,
                'subject' => 'Team Player Withdrawn – '.$player->full_name.' ('.$event->name.')', 'body' => $body])->values()->all();
    }

    public function record(Activity $activity): void
    {
        $activity = $activity->fresh();
        if (! $activity) return;
        foreach ($this->messages($activity) as $message) {
            if (BulkEmailLog::where('mail_type', 'team_withdrawal_notification')->where('related_type', Activity::class)
                ->where('related_id', $activity->id)->where('recipient_email', $message['email'])->exists()) continue;
            app(BulkMailDispatcher::class)->dispatch('team_withdrawal_notification', $activity, [['email' => $message['email'], 'name' => 'Withdrawal notification']],
                ['event_id' => $message['event_id'], 'campaign_key' => 'team-withdrawal:'.$activity->id,
                    'created_by' => $activity->causer_id, 'system_initiated' => true, 'manual_retry_only' => true,
                    'subject' => $message['subject'], 'body' => $message['body']]);
        }
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        if ($log->mail_type !== 'team_withdrawal_notification' || $log->related_type !== Activity::class
            || ! ($activity = Activity::find($log->related_id))) return null;
        foreach ($this->messages($activity) as $message) {
            if ($log->recipient_email === $message['email'] && (int) ($log->payload['event_id'] ?? 0) === (int) $message['event_id']
                && ($log->payload['subject'] ?? null) === $message['subject'] && ($log->payload['body'] ?? null) === $message['body']) {
                return (new BulkEventMail($message['subject'], $message['body'], config('mail.from.name', 'Cape Tennis'), config('mail.from.address')))
                    ->with('team_withdrawal_log_id', $log->id);
            }
        }
        return null;
    }
}
