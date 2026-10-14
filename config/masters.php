<?php

return [
    // A PayFast redirect is a temporary checkout, not a confirmed entry.
    // Abandoned Masters checkouts return to "Register" after this window.
    'pending_payment_minutes' => \App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES,
];
