<?php

namespace App\Services\Draw;

use App\Models\Event;
use Illuminate\Support\Facades\Schema;

/** Request-scoped schema readiness for optional certification badges. */
class CtrCertificationStatus
{
    private ?bool $available = null;
    private ?bool $plannerAvailable = null;

    public function available(): bool
    {
        return $this->available ??= Schema::hasTable('ctr_event_certifications');
    }

    public function certified(Event $event): bool
    {
        return $this->available() && (bool) $event->ctrCertification?->certified;
    }

    public function plannerAvailable(): bool
    {
        return $this->plannerAvailable ??= $this->available() && Schema::hasTable('ctr_draw_plans');
    }
}
