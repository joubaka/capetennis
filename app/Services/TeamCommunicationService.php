<?php

namespace App\Services;

use App\Mail\TeamActionMail;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class TeamCommunicationService
{
    public function player(TeamPaymentOrder $order, string $action, array $details = []): void
    {
        $order->loadMissing(['user', 'event', 'player']);
        if ($order->user?->email) {
            Mail::to($order->user->email)->queue(new TeamActionMail($order, $action, $details));
        }
    }

    public function withdrawal(TeamPaymentOrder $order, array $details = []): void
    {
        $this->player($order, 'withdrawal', $details);
        $order->loadMissing('event.admins');

        $recipients = User::role('super-user')->pluck('email')
            ->merge($order->event?->admins?->pluck('email') ?? collect())
            ->filter()
            ->map(fn ($email) => strtolower($email))
            ->unique()
            ->reject(fn ($email) => $email === strtolower((string) $order->user?->email));

        foreach ($recipients as $email) {
            Mail::to($email)->queue(new TeamActionMail($order, 'withdrawal', $details + ['admin_copy' => true]));
        }
    }
}
