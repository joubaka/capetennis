<?php

namespace App\Actions;

use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class AttemptToAuthenticateWithFeedback
{
    protected $guard;
    protected $limiter;

    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter)
    {
        $this->guard   = $guard;
        $this->limiter = $limiter;
    }

    public function handle($request, $next)
    {
        if (Fortify::$authenticateUsingCallback) {
            return $this->handleUsingCustomCallback($request, $next);
        }

        if ($this->guard->attempt(
            $request->only(Fortify::username(), 'password'),
            $request->boolean('remember'))
        ) {
            return $next($request);
        }

        $this->throwFailedAuthenticationException($request);
    }

    protected function handleUsingCustomCallback($request, $next)
    {
        $user = call_user_func(Fortify::$authenticateUsingCallback, $request);

        if (! $user) {
            $this->fireFailedEvent($request);
            return $this->throwFailedAuthenticationException($request);
        }

        $this->guard->login($user, $request->boolean('remember'));
        return $next($request);
    }

    protected function throwFailedAuthenticationException($request)
    {
        $this->limiter->increment($request);

        $usernameField = Fortify::username();
        $usernameValue = $request->input($usernameField);

        $userModel = config('auth.providers.users.model');
        $user = $userModel::where($usernameField, $usernameValue)->first();

        $message = __('auth.failed');

        if (! $user) {
            $message = __("No account found with that :field.", ['field' => $usernameField]);
        } elseif (! Hash::check($request->password, $user->password)) {
            $message = __("Incorrect password, please try again.");
        } elseif (property_exists($user, 'active') && ! $user->active) {
            $message = __("Your account is inactive. Contact support.");
        }

        throw ValidationException::withMessages([
            $usernameField => [$message],
        ]);
    }

    protected function fireFailedEvent($request)
    {
        event(new Failed(config('fortify.guard'), null, [
            Fortify::username() => $request->{Fortify::username()},
            'password' => $request->password,
        ]));
    }
}
