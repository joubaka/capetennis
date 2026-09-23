<?php

namespace App\Http\Responses;

use App\Support\Auth\PostLoginDestination;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(private readonly PostLoginDestination $destination)
    {
    }

    public function toResponse($request)
    {
        $destination = $this->destination->resolve($request, $request->user());

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect($destination);
    }
}
