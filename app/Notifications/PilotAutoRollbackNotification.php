<?php

namespace App\Notifications;

use App\Models\Draw;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to super-users when a canonical pilot draw is automatically
 * rolled back to hybrid due to a threshold breach.
 */
class PilotAutoRollbackNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Draw   $draw,
        public readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $drawName  = $this->draw->drawName ?? "Draw #{$this->draw->id}";
        $eventName = optional($this->draw->event)->name ?? "Event #{$this->draw->event_id}";

        return (new MailMessage)
            ->subject("[Cape Tennis] ⚠️ Pilot Auto-Rollback: {$drawName}")
            ->greeting("Pilot Auto-Rollback Alert")
            ->line("A canonical RR pilot draw has been automatically downgraded to hybrid mode.")
            ->line("**Draw:** {$drawName} (#{$this->draw->id})")
            ->line("**Event:** {$eventName}")
            ->line("**Reason:** {$this->reason}")
            ->line("The draw's engine_mode has been set to **hybrid** and the pilot approval has been revoked.")
            ->line("Please review the pilot audit logs and resolve any issues before re-enabling canonical mode.")
            ->salutation("Cape Tennis Platform — Automated Safety System");
    }
}
