<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;

class HostProposalProjection
{
    public function defaults(): array
    {
        return ['convenor_cost' => '500.00', 'rental_hours' => '4.00', 'rental_rate' => '50.00', 'other_costs' => '0.00'];
    }

    public function calculate(array $plan, array $input, bool $useSuggestedLimits = false): array
    {
        $get = fn ($key, $fallback) => array_key_exists($key, $input) ? $input[$key] : $fallback;
        $entries = $useSuggestedLimits ? $plan['limits'] : $get('limits', $plan['limits']);
        $validator = Validator::make(['limits' => $entries], ['limits' => 'required|array|size:4', 'limits.*' => 'required|integer|between:0,1600|multiple_of:4']);
        $validator->after(function ($validator) use ($entries, $plan) {
            if (is_array($entries)) {
                if (array_keys($entries) !== [0, 1, 2, 3]) $validator->errors()->add('limits', 'Supply all four entry limits in order.');
                foreach ($plan['limits'] as $i => $cap) {
                    if (isset($entries[$i]) && is_numeric($entries[$i]) && $entries[$i] > $cap) $validator->errors()->add('limits.'.$i, 'Entry limits cannot exceed the court suggestions.');
                }
            }
        });
        $entries = array_map('intval', $validator->validate()['limits']);
        $defaults = $this->defaults();
        // Commercial deductions and rented court count are owned by the server.
        $assumptions = ['entries' => $entries, 'limits' => $plan['limits'],
            'fees' => $get('fees', app(PanarottisCalculator::class)->defaults()['fees']),
            'processing_percent' => '5.00', 'platform_fee' => '15.00',
            'convenor' => $get('convenor_cost', $defaults['convenor_cost']),
            'courts' => $plan['assigned_total'], 'hours' => $get('rental_hours', $defaults['rental_hours']),
            'rental' => $get('rental_rate', $defaults['rental_rate']), 'other_costs' => $get('other_costs', $defaults['other_costs'])];
        return ['assumptions' => $assumptions] + app(PanarottisCalculator::class)->calculate($assumptions);
    }
}
