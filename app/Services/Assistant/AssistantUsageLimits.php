<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\RateLimiter;

final class AssistantUsageLimits
{
    public function consume(int $userId, string $kind): void
    {
        $key = 'event-assistant:'.$kind.':'.$userId.':'.now()->toDateString();
        abort_if(RateLimiter::tooManyAttempts($key, (int) config('event-assistant.daily_'.$kind.'_limit', 100)), 429, 'Your assistant daily limit has been reached.');
        RateLimiter::hit($key, max(1, (int) now()->diffInSeconds(now()->endOfDay())));
    }
}
