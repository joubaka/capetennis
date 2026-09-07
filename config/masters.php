<?php

return [
    // A PayFast redirect is a temporary checkout, not a confirmed entry.
    // Abandoned Masters checkouts return to "Register" after this window.
    'pending_payment_minutes' => (int) env('MASTERS_PENDING_PAYMENT_MINUTES', 60),
];
