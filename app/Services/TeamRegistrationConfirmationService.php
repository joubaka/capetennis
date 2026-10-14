<?php

namespace App\Services;

use App\Mail\{BulkEventMail, TeamActionMail};
use App\Models\{BulkEmailLog, EventCommunicationBatch, SiteSetting, TeamPaymentOrder, TeamPlayer};
use Illuminate\Support\Facades\DB;

/** Automatic receipts are limited to the canonical paid order and its payer. */
final class TeamRegistrationConfirmationService
{
    public function record(TeamPaymentOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = TeamPaymentOrder::whereKey($order->id)->lockForUpdate()->first();
            if (! $order || ! ($content = $this->content($order))) return;
            // A replay must neither queue another copy nor send an existing reviewed draft.
            if (BulkEmailLog::where('mail_type', 'team_payment_confirmation')->where('related_type', TeamPaymentOrder::class)->where('related_id', $order->id)->exists()
                || EventCommunicationBatch::where('source_key', 'like', 'team-order:'.$order->id.':registration:%')->exists()) return;
            app(BulkMailDispatcher::class)->dispatch('team_payment_confirmation', $order,
                [['email' => $content['email'], 'name' => $order->user->name]],
                ['event_id' => $order->event_id, 'order_id' => $order->id, 'created_by' => null, 'system_initiated' => true,
                    'campaign_key' => 'team-payment-confirmation:'.$order->id, 'manual_retry_only' => true,
                    'subject' => $content['subject'], 'body' => $content['body'], 'from_name' => $content['from_name'], 'reply_to' => $content['reply_to'],
                    'confirmation_fingerprint' => $content['fingerprint']]);
        });
    }

    private function content(TeamPaymentOrder $order): ?array
    {
        if (! SiteSetting::emailEnabled('player_email_on_team_registration') || (int) $order->getRawOriginal('pay_status') !== 1
            || $order->withdrawn_at || $order->hasRefund() || $order->refunded_at || $order->refund_waived_at) return null;
        $order->load(['event', 'team', 'user', 'effectivePlayer']);
        if (! $order->event || ! $order->team || ! $order->effectivePlayer || ! $order->user
            || ! app(EventTeamScope::class)->query($order->event)->whereKey($order->team_id)->exists()
            || ! TeamPlayer::withoutGlobalScopes()->where('team_id', $order->team_id)->where('player_id', $order->effective_player_id)->where('pay_status', 1)->exists()) return null;
        $email = mb_strtolower(trim($order->user->email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $mail = new TeamActionMail($order, 'registration');
        $content = ['email' => $email, 'subject' => $mail->envelope()->subject, 'body' => $mail->render(),
            'from_name' => config('mail.from.name', 'Cape Tennis'), 'reply_to' => config('mail.from.address')];
        $content['fingerprint'] = hash('sha256', json_encode([$order->id, $order->event_id, $order->team_id, $order->effective_player_id, $order->user_id, $order->total_amount, $content], JSON_THROW_ON_ERROR));
        return $content;
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        $order = TeamPaymentOrder::find($log->payload['order_id'] ?? 0);
        if ($log->mail_type !== 'team_payment_confirmation' || ! $order || $log->related_type !== TeamPaymentOrder::class
            || (int) $log->related_id !== (int) $order->id || (int) ($log->payload['event_id'] ?? 0) !== (int) $order->event_id
            || ! ($content = $this->content($order)) || $log->recipient_email !== $content['email']
            || ! hash_equals($content['fingerprint'], $log->payload['confirmation_fingerprint'] ?? '')
            || ($log->payload['subject'] ?? null) !== $content['subject'] || ($log->payload['body'] ?? null) !== $content['body']) return null;
        return (new BulkEventMail($content['subject'], $content['body'], $content['from_name'], $content['reply_to']))
            ->with('team_confirmation_log_id', $log->id);
    }
}
