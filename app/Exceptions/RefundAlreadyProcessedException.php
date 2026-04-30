<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown inside a DB transaction when a pessimistic lock reveals
 * that a refund has already been processed or is already pending.
 * The calling controller catches this and returns an appropriate
 * response without marking it as an application error.
 */
class RefundAlreadyProcessedException extends RuntimeException
{
    public function __construct(string $message = 'Refund already processed or pending.')
    {
        parent::__construct($message);
    }
}
