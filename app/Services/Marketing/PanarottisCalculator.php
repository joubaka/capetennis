<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;

class PanarottisCalculator
{
    public const CATEGORIES = ['Red Ball', 'Orange Ball', 'Green Ball', 'Yellow Ball'];

    public function defaults(): array
    {
        return ['limits' => [16, 16, 12, 8], 'entries' => [16, 16, 12, 8], 'fees' => ['200.00', '250.00', '250.00', '300.00'],
            'processing_percent' => '5.00', 'platform_fee' => '15.00', 'convenor' => '500.00',
            'courts' => 10, 'hours' => '4.00', 'rental' => '50.00', 'other_costs' => '0.00'];
    }

    public function calculate(array $input): array
    {
        $money = ['required', 'regex:/^\d{1,6}(\.\d{1,2})?$/', 'numeric', 'between:0,100000'];
        $validator = Validator::make($input, [
            'limits' => 'required|array|size:4', 'limits.*' => 'required|integer|between:0,1600',
            'entries' => 'required|array|size:4', 'entries.*' => 'required|integer|between:0,1600',
            'fees' => 'required|array|size:4', 'fees.*' => $money,
            'processing_percent' => ['required', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'platform_fee' => $money, 'convenor' => $money, 'courts' => 'required|integer|between:0,100',
            'hours' => ['required', 'numeric', 'between:0,24', 'regex:/^\d{1,2}(\.\d{1,2})?$/'],
            'rental' => $money, 'other_costs' => $money,
        ]);
        $validator->after(function ($validator) use ($input) {
            foreach (['limits', 'entries', 'fees'] as $key) {
                if (isset($input[$key]) && is_array($input[$key]) && array_keys($input[$key]) !== [0, 1, 2, 3]) {
                    $validator->errors()->add($key, 'Supply all four categories in order.');
                }
            }
            foreach (range(0, 3) as $i) {
                if (isset($input['entries'][$i], $input['limits'][$i]) && is_numeric($input['entries'][$i]) && is_numeric($input['limits'][$i]) && $input['entries'][$i] > $input['limits'][$i]) {
                    $validator->errors()->add("entries.$i", 'Paid entries cannot exceed the category limit.');
                }
            }
        });
        $data = $validator->validate();
        $rows = [];
        foreach (self::CATEGORIES as $i => $name) {
            $entries = (int) $data['entries'][$i];
            $fee = $this->hundredths((string) $data['fees'][$i]);
            // Estimate per-entry processing rounded half-up to cents, then multiply.
            $processing = intdiv($fee * $this->hundredths((string) $data['processing_percent']) + 5000, 10000) * $entries;
            $gross = $entries * $fee;
            $platform = $entries * $this->hundredths((string) $data['platform_fee']);
            $rows[] = compact('name', 'entries', 'fee', 'gross', 'processing', 'platform') + ['retained' => $gross - $processing - $platform];
        }
        $totals = [];
        foreach (['entries', 'gross', 'processing', 'platform', 'retained'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }
        $totals['convenor'] = $this->hundredths((string) $data['convenor']);
        $totals['rental'] = intdiv((int) $data['courts'] * $this->hundredths((string) $data['hours']) * $this->hundredths((string) $data['rental']) + 50, 100);
        $totals['other_costs'] = $this->hundredths((string) $data['other_costs']);
        $totals['remaining'] = $totals['retained'] - $totals['convenor'] - $totals['rental'] - $totals['other_costs'];
        return compact('rows', 'totals');
    }

    private function hundredths(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
