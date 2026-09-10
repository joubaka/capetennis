<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Models\SiteSetting;

final class ClothingPriceService
{
    /**
     * @return array{subtotal: float, payfast_fee: float, total: float}
     */
    public function totals(float $subtotal): array
    {
        $subtotal = round(max(0, $subtotal), 2);
        $fee = $subtotal > 0 ? SiteSetting::calculatePayfastFee($subtotal) : 0.0;

        return [
            'subtotal' => $subtotal,
            'payfast_fee' => $fee,
            'total' => round($subtotal + $fee, 2),
        ];
    }

    /**
     * Values used by the browser previews. The server recalculates all payable
     * amounts and never trusts these client-side values.
     *
     * @return array{percentage: float, flat: float, vat: float}
     */
    public function settings(): array
    {
        return [
            'percentage' => SiteSetting::getPayfastFeePercentage(),
            'flat' => (float) SiteSetting::get('payfast_fee_flat', 2.00),
            'vat' => (float) SiteSetting::get('payfast_vat_rate', 14),
        ];
    }
}
