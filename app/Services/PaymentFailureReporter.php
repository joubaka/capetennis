<?php

namespace App\Services;

use App\Mail\PaymentFailureAlertMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PaymentFailureReporter
{
    public function report(string $operation, array $context = [], ?\Throwable $exception = null): string
    {
        $reference = (string) Str::uuid();
        $details = array_filter(array_merge([
            'reference' => $reference,
            'operation' => $operation,
            'environment' => app()->environment(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_id' => auth()->id(),
        ], $context));

        if ($exception) {
            $details['exception'] = get_class($exception);
            $details['error'] = $exception->getMessage();
            $details['file'] = $exception->getFile();
            $details['line'] = $exception->getLine();
            $details['trace'] = Str::limit($exception->getTraceAsString(), 4000, '...');
        }

        Log::error('Payment/registration failure', $details);

        $recipient = config('services.payment_failure_alert.recipient');
        if ($recipient && config('services.payment_failure_alert.enabled', true)) {
            try {
                Mail::to($recipient)->sendNow(new PaymentFailureAlertMail($details));
            } catch (\Throwable $mailException) {
                Log::critical('Payment failure alert email could not be sent', [
                    'reference' => $reference,
                    'recipient' => $recipient,
                    'error' => $mailException->getMessage(),
                ]);
            }
        }

        return $reference;
    }
}
