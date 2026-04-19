<?php

namespace App\Observers;

use App\Models\CategoryEventRegistration;
use Illuminate\Support\Facades\Log;

class RegistrationObserver
{
    /**
     * Handle the CategoryEventRegistration "updated" event.
     * Logs when a registration's status transitions to withdrawn.
     */
    public function updated(CategoryEventRegistration $registration): void
    {
        if (
            $registration->isDirty('status')
            && $registration->status === 'withdrawn'
            && $registration->getOriginal('status') !== 'withdrawn'
        ) {
            Log::info('Registration withdrawn', [
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'refund_status' => $registration->refund_status,
                'withdrawn_at' => $registration->withdrawn_at,
            ]);
        }

        if (
            $registration->isDirty('refund_status')
            && $registration->refund_status === 'completed'
        ) {
            Log::info('Registration refund completed', [
                'registration_id' => $registration->id,
                'user_id' => $registration->user_id,
                'refund_method' => $registration->refund_method,
                'refund_net' => $registration->refund_net,
            ]);
        }
    }
}
