<?php

namespace App\Jobs;

use App\Services\Performance\PlayerPlusService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StopPlayerPlusSubscription implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 300;

    public function __construct(public int $subscriptionId) {}

    public function handle(PlayerPlusService $service): void
    {
        $service->stopRequestedSubscription($this->subscriptionId);
    }
}
