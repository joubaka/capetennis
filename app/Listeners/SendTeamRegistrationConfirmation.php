<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Models\TeamPaymentOrder;
use App\Services\TeamCommunicationService;

class SendTeamRegistrationConfirmation
{
    public function __construct(private TeamCommunicationService $communications)
    {
    }

    public function handle(PaymentCompleted $event): void
    {
        if (! $event->payment instanceof TeamPaymentOrder) {
            return;
        }

        $this->communications->player($event->payment, 'registration');
    }
}
