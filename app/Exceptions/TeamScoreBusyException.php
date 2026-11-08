<?php

namespace App\Exceptions;

use PDOException;
use RuntimeException;

final class TeamScoreBusyException extends RuntimeException
{
    public function __construct(PDOException $cause)
    {
        parent::__construct('Team score transaction interrupted by database contention.', 0, $cause);
    }
}
