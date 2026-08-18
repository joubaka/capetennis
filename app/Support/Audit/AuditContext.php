<?php

namespace App\Support\Audit;

use Illuminate\Http\Request;

class AuditContext
{
    private ?string $requestId = null;
    private ?string $journeyId = null;
    private ?string $previousRequestId = null;
    private ?Request $request = null;

    public function set(Request $request, string $requestId, ?string $journeyId, ?string $previousRequestId): void
    {
        $this->request = $request;
        $this->requestId = $requestId;
        $this->journeyId = $journeyId;
        $this->previousRequestId = $previousRequestId;
    }

    public function clear(): void
    {
        $this->request = null;
        $this->requestId = null;
        $this->journeyId = null;
        $this->previousRequestId = null;
    }

    public function request(): ?Request { return $this->request; }
    public function requestId(): ?string { return $this->requestId; }
    public function journeyId(): ?string { return $this->journeyId; }
    public function previousRequestId(): ?string { return $this->previousRequestId; }
}
