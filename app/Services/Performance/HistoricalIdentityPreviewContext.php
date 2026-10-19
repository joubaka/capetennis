<?php

namespace App\Services\Performance;

/** Explicit, request-local counterfactual. Never an original participant attestation. */
final readonly class HistoricalIdentityPreviewContext
{
    public function __construct(private array $identities) {}

    public function identity(int $event, int $row, int $side): ?array
    {
        return $this->identities[$event][$row][$side] ?? null;
    }
}
