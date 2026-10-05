<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
class MailFailureOutcome
{
    public function record(BulkEmailLog $log, \Throwable $error, bool $transportStarted): void
    {
        // Only an explicit protocol rejection proves nonacceptance. A timeout or
        // disconnect can follow acceptance and must not authorize another send.
        $rejected = $error instanceof UnexpectedResponseException && $error->getCode() >= 400 && $error->getCode() <= 599;
        if ($transportStarted && !$rejected) {
            $log->update(['status' => 'acceptance_unknown', 'error_message' => 'Mail-server acceptance is uncertain. Verify with the provider before retrying.']);
        } else {
            $log->markAsFailed('The message could not be sent. Review the message, recipient and queue before retrying.');
        }
    }
}
