<?php

namespace App\Exceptions;

/** The private-preview message was rejected before any transport could run. */
class SelectionPreviewMailAuthorizationException extends \Symfony\Component\HttpKernel\Exception\HttpException
{
    public function __construct()
    {
        parent::__construct(409, 'This private preview email is no longer authorized.');
    }
}
