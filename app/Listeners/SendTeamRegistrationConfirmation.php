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
        if (! $event->payment instanceof TeamPaymentOrder || ($event->context['suppress_registration_confirmation'] ?? false)) {
            return;
        }

        $this->communications->player($event->payment, 'registration');
    }
}
