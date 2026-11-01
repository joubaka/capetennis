<?php

namespace App\Services\Performance;

use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, PlayerPlusMembership, PlayerPlusOrder, SiteSetting};
use App\Services\BulkMailDispatcher;
use Illuminate\Support\Facades\DB;

/** Payer messages and operator alerts use managed delivery, durable deduplication and send-time checks. */
class PlayerPlusNotifications
{
    public const TYPES = ['activation', 'renewal', 'reconciliation'];

    public function record(PlayerPlusOrder $order, string $kind, bool $repairSkipped = false): array
    {
        return DB::transaction(function () use ($order, $kind, $repairSkipped) {
            $order = PlayerPlusOrder::lockForUpdate()->findOrFail($order->id);
            return $this->reserve($order, $kind, $repairSkipped);
        }, 3);
    }

    private function reserve(PlayerPlusOrder $order, string $kind, bool $repairSkipped): array
    {
        $content = $this->content($order, $kind);
        if (! $content) {
            // A missing payer/profile must not occupy the bounded repair batch forever.
            if ($kind === 'activation' && $order->activated_at && $order->pay_status && $order->payfast_paid
                && ! $order->mailLogs()->where('mail_type', 'player_plus_activation')->exists()) {
                BulkEmailLog::firstOrCreate(['deduplication_key' => hash('sha256', 'player-plus:unavailable-activation:'.$order->id)],
                    ['mail_type' => 'player_plus_activation', 'related_type' => PlayerPlusOrder::class, 'related_id' => $order->id,
                        'recipient_email' => mb_strtolower(trim((string) $order->user?->email)), 'status' => 'skipped', 'skipped_at' => now(),
                        'error_message' => 'Player Plus confirmation could not be prepared. Review the payer contact and purchased term before an explicit repair.',
                        'payload' => ['kind' => $kind, 'system_initiated' => true, 'manual_retry_only' => true]]);
            }
            return ['queued' => 0];
        }
        $campaign = 'player-plus:'.$order->id.':'.$kind.':'.$content['revision'];
        $baseCampaign = $campaign;
        $existing = $order->mailLogs()->where('mail_type', 'player_plus_'.$kind)
            ->where(fn ($query) => $query->where('payload->campaign_key', $baseCampaign)->orWhere('payload->base_campaign_key', $baseCampaign))->get();
        if ($existing->isNotEmpty()) {
            // Explicit repair can regenerate only known pre-transport skips. Never recreate an accepted,
            // ambiguous, failed, actively sending or already queued attempt, even after a contact change.
            if (! $repairSkipped || $existing->contains(fn ($log) => $log->status !== 'skipped' || $log->sent_at || $log->accepted_at)) return ['queued' => 0];
            $campaign .= ':repair:'.$content['fingerprint'];
            if ($existing->contains(fn ($log) => ($log->payload['campaign_key'] ?? '') === $campaign)) return ['queued' => 0];
        }
        return app(BulkMailDispatcher::class)->dispatch('player_plus_'.$kind, $order,
            [['email' => $content['email'], 'name' => $content['name']]],
            ['system_initiated' => true, 'manual_retry_only' => true, 'campaign_key' => $campaign, 'base_campaign_key' => $baseCampaign,
                'kind' => $kind, 'content_fingerprint' => $content['fingerprint']]);
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        $kind = $log->payload['kind'] ?? '';
        if (! in_array($kind, self::TYPES, true) || $log->mail_type !== 'player_plus_'.$kind
            || $log->related_type !== PlayerPlusOrder::class) return null;
        $order = PlayerPlusOrder::find($log->related_id);
        $content = $order ? $this->content($order, $kind) : null;
        if (! $content || $log->recipient_email !== $content['email']
            || ! hash_equals($content['fingerprint'], $log->payload['content_fingerprint'] ?? '')) return null;
        return (new BulkEventMail($content['subject'], $content['body'], config('mail.from.name', 'Cape Tennis'), 'support@capetennis.co.za'))
            ->with('player_plus_notification_log_id', $log->id);
    }

    private function content(PlayerPlusOrder $order, string $kind): ?array
    {
        if (! in_array($kind, self::TYPES, true)) return null;
        $order->loadMissing(['user', 'player']);
        if ($kind === 'reconciliation') {
            if (! filled($order->reconciliation_reason)) return null;
            $email = mb_strtolower(trim((string) SiteSetting::get('admin_notification_email', '')));
            $revision = app(PlayerPlusReconciliation::class)->fingerprint($order);
            if (app(PlayerPlusReconciliation::class)->reviews($order)->where('metadata->payment_fingerprint', $revision)->exists()) return null;
            $subject = 'Player Plus payment needs review · P'.$order->id;
            $body = '<p>Verified money for Player Plus order P'.e($order->id).' requires reconciliation.</p>'
                .'<p><a href="'.e(route('superadmin.player-plus.show', $order)).'">Review payment evidence</a></p>'
                .'<p>Review every provider receipt. No membership or refund decision is made by this alert.</p>';
            $name = 'Cape Tennis support';
        } else {
            if (! $order->user || ! $order->player || ! $order->activated_at || ! $order->term_ends_at
                || $order->status !== 'completed' || ! $order->pay_status || ! $order->payfast_paid) return null;
            if ($kind === 'renewal' && (! PlayerPlusService::salesEnabled() || $order->term_ends_at->lte(now())
                || $order->term_ends_at->gt(now()->addDays(14))
                || ! PlayerPlusMembership::where('player_id', $order->player_id)->where('order_id', $order->id)
                    ->where('starts_at', '<=', now())->where('ends_at', $order->term_ends_at)->exists())) return null;
            $email = mb_strtolower(trim((string) $order->user->email));
            $name = $order->user->name;
            $revision = $order->term_ends_at->toIso8601String();
            $end = e($order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i')).' SAST';
            $subject = $kind === 'activation' ? 'Player Plus payment confirmed · P'.$order->id : 'Player Plus expires soon · P'.$order->id;
            $body = '<p>'.e($order->player->full_name).' · Player Plus order P'.e($order->id).'</p>'
                .($kind === 'activation' ? '<p>Your once-off R'.e(PlayerPlusService::amount($order->price_cents)).' payment was verified for the purchased membership term ending '.$end.'. Check the order for its current status.</p>'
                    : '<p>The membership you purchased ends on '.$end.'. There is no automatic renewal or charge. You can renew manually after expiry at the price shown then.</p>')
                .'<p><a href="'.e(route('player-plus.orders.show', $order)).'">View your order and membership details</a></p>'
                .'<p>Private benefits belong to the named player and their currently linked accounts. Sponsorship does not link a player or grant private access.</p>'
                .'<p>Questions or payment problems? Contact support@capetennis.co.za with order P'.e($order->id).'.</p>';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $content = compact('email', 'name', 'revision', 'subject', 'body');
        $content['fingerprint'] = hash('sha256', json_encode([$order->id, $kind, $content], JSON_THROW_ON_ERROR));
        return $content;
    }
}
