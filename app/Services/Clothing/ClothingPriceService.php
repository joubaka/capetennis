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
     * Resolve the clothing price that produces the requested one-item total.
     * PayFast rounds its fee to cents, so inspect the neighbouring cent values
     * and return the closest server-calculated result.
     *
     * @return array{subtotal: float, payfast_fee: float, total: float}
     */
    public function totalsFromFinalAmount(float $finalAmount): array
    {
        $finalAmount = round(max(0, $finalAmount), 2);
        if ($finalAmount === 0.0) {
            return $this->totals(0);
        }

        $settings = $this->settings();
        $vatMultiplier = 1 + ($settings['vat'] / 100);
        $grossMultiplier = 1 + (($settings['percentage'] / 100) * $vatMultiplier);
        $grossFlat = $settings['flat'] * $vatMultiplier;
        $estimate = max(0, ($finalAmount - $grossFlat) / $grossMultiplier);

        $best = $this->totals(round($estimate, 2));
        for ($offset = -5; $offset <= 5; $offset++) {
            $candidate = $this->totals(round($estimate + ($offset / 100), 2));
            if (abs($candidate['total'] - $finalAmount) < abs($best['total'] - $finalAmount)) {
                $best = $candidate;
            }
        }

        return $best;
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
