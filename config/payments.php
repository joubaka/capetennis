<?php

return [
  // PayFast (example) — set yours
  'payfast' => [
    'percent' => 0.029,   // 2.9%
    'fixed' => 2.00,    // R2.00
    'min' => 0.00,    // optional floors
  ],

  // Your platform fee (example)
  'cape' => [
    'mode' => 'percent', // 'percent' or 'flat'
    'value' => 0.05,      // 5% (or flat R amount if mode='flat')
    'min' => 0.00,
  ],

  // Which transaction types “bring money in” vs “send money out”
  // Adjust to your codes
  'signs' => [
    'in' => ['REG_PAY', 'TOPUP'],
    'out' => ['REG_WITHDRAW_PAID', 'REG_PARTIAL_REFUND', 'CHARGEBACK'],
    // neutral/non-cash (still listed): REG_COMP, REG_WITHDRAW_FREE, REG_CANCEL_TIMEOUT, etc.
    'neutral' => ['REG_COMP', 'REG_WITHDRAW_FREE', 'REG_CANCEL_TIMEOUT', 'REG_TRANSFER', 'REG_PRICE_ADJUST'],
  ],
];
