<?php

namespace App\Services;

use App\Mail\WithdrawalAdminMail;
use App\Mail\WithdrawalPlayerMail;
use App\Mail\DailyWithdrawalSummaryMail;
use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailable;

/** Rebuild only canonical, recipient-scoped completed individual withdrawal notices. */
final class IndividualWithdrawalMailAuthorization
{
    public function expected(MessageSending $mail): ?Mailable
    {
        $class = $mail->data['__laravel_mailable'] ?? null;
        if ($class === DailyWithdrawalSummaryMail::class) return $this->summary($mail);
        if (! in_array($class, [WithdrawalAdminMail::class, WithdrawalPlayerMail::class], true)) return null;
        $source = $mail->data['registration'] ?? null;
        if (! $source instanceof CategoryEventRegistration) return null;
        $registration = CategoryEventRegistration::find($source->id);
        if (! $registration || ! $registration->withdrawn_at
            || ! in_array($registration->status, ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded'], true)
            || ! $registration->categoryEvent?->event) return null;
        $registration->loadMissing(['players', 'user', 'categoryEvent.event.admins']);
        if (count($mail->message->getTo()) !== 1) return null;
        $recipient = mb_strtolower($mail->message->getTo()[0]->getAddress());
        if ($class === WithdrawalAdminMail::class) {
            if (! SiteSetting::emailEnabled('email_on_withdrawal')) return null;
            $allowed = User::role('super-user')->pluck('email')->merge($registration->categoryEvent->event->admins->pluck('email'));
        } else {
            if (! SiteSetting::emailEnabled('player_email_on_withdrawal')) return null;
            $allowed = collect([$registration->players->first()?->email ?? $registration->user?->email]);
        }
        if (! $allowed->filter()->map(fn ($email) => mb_strtolower(trim($email)))->contains($recipient)) return null;
        $initiatedBy = $mail->data['initiatedBy'] ?? 'self';
        if (! in_array($initiatedBy, ['self', 'admin'], true)) return null;
        return new $class($registration, $initiatedBy);
    }

    private function summary(MessageSending $mail): ?Mailable
    {
        if (! SiteSetting::emailEnabled('admin_email_on_daily_withdrawal_summary')) return null;
        $event = ($mail->data['event'] ?? null) instanceof \App\Models\Event ? $mail->data['event']->fresh() : null;
        $withdrawals = $mail->data['withdrawals'] ?? null;
        $from = $mail->data['periodStart'] ?? null;
        $until = $mail->data['periodEnd'] ?? null;
        if (! $event || ! $withdrawals instanceof \Illuminate\Support\Collection || $withdrawals->isEmpty()
            || ! $from instanceof \Illuminate\Support\Carbon || ! $until instanceof \Illuminate\Support\Carbon
            || ! $from->copy()->startOfDay()->equalTo($from) || ! $from->copy()->addDay()->equalTo($until)
            || count($mail->message->getTo()) !== 1) return null;
        $recipients = User::role('super-user')->pluck('email')->merge($event->admins->pluck('email'))
            ->filter()->map(fn ($email) => mb_strtolower(trim($email)));
        if (! $recipients->contains(mb_strtolower($mail->message->getTo()[0]->getAddress()))) return null;
        $fresh = collect();
        foreach ($withdrawals as $source) {
            if (! $source instanceof CategoryEventRegistration) return null;
            $entry = CategoryEventRegistration::withTrashed()->find($source->id);
            if (! $entry || (int) $entry->categoryEvent?->event_id !== (int) $event->id || $entry->status !== 'withdrawn'
                || ! $entry->withdrawn_at || $entry->withdrawn_at->lt($from) || ! $entry->withdrawn_at->lt($until)) return null;
            $fresh->push($entry);
        }
        return new DailyWithdrawalSummaryMail($event, $fresh, $from, $until);
    }
}
