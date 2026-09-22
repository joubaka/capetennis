<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
  public function toResponse($request)
  {
    // Highest priority: an explicitly requested local path (for modal flows).
    if ($request->filled('redirect') && $this->isSafeLocalPath((string) $request->redirect)) {
      return redirect((string) $request->redirect);
    }

    // Keep Fortify's intended destination. When none exists, event admins land
    // in their work hub while ordinary participant accounts retain home.
    $fallback = $request->user()?->adminEvents()->exists()
      ? route('backend.dashboard')
      : '/';

    return redirect()->intended($fallback);
  }

  private function isSafeLocalPath(string $path): bool
  {
    if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, '\\')) {
      return false;
    }

    $decoded = rawurldecode($path);

    return ! str_starts_with($path, '//')
      && ! str_starts_with($decoded, '//')
      && ! str_starts_with($decoded, '/\\')
      && ! preg_match('/[\x00-\x1F\x7F]/', $decoded);
  }
}
