<?php

namespace App\Notifications;

use App\Models\DisciplinaryCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisciplinaryCaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DisciplinaryCase $case,
        public string $subjectLine,
        public string $messageLine,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subjectLine)
            ->greeting('Cape Tennis disciplinary notice')
            ->line("Case: {$this->case->case_number}")
            ->line($this->messageLine)
            ->action('View private case', route('disciplinary.my-cases.show', $this->case))
            ->line('This information is confidential. Please do not forward it.');
    }
}
