<?php

namespace SportStack\Assistant\Contracts;

/** An interpreter returns data; application adapters own every action. */
interface IntentProvider
{
    public function available(): bool;

    public function interpret(string $message, array $context, array $previous): array;
}
