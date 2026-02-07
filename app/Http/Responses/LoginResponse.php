<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
  public function toResponse($request)
  {
    // ✅ Highest priority: explicit redirect param (your modal flow)
    if ($request->filled('redirect') && str_starts_with($request->redirect, '/')) {
      return redirect($request->redirect);
    }

    // ✅ Fallback to intended URL or HOME
    return redirect()->intended('/');
  }
}
