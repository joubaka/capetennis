<?php

namespace App\Http\Responses;

use App\Support\Auth\PostLoginDestination;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
  public function __construct(private readonly PostLoginDestination $destination)
  {
  }

  public function toResponse($request)
  {
    return redirect($this->destination->resolve($request, $request->user()));
  }
}
