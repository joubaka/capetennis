<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HostCourtCapacityPlanner
{
    public function defaults(): array
    {
        return ['courts' => 10, 'assigned_courts' => [1, 4, 3, 2]];
    }

    public function calculate(array $input): array
    {
        $data = Validator::make($input, [
            'courts' => 'required|integer|between:1,100',
            'assigned_courts' => 'required|array|size:4',
            'assigned_courts.*' => 'required|integer|between:0,100',
        ])->validate();
        if (array_keys($data['assigned_courts']) !== [0, 1, 2, 3]) {
            throw ValidationException::withMessages(['assigned_courts' => 'Supply courts for all four categories in order.']);
        }
        $assigned = array_map('intval', $data['assigned_courts']);
        $totalAssigned = array_sum($assigned);
        if ($totalAssigned < 1 || $totalAssigned > (int) $data['courts']) {
            throw ValidationException::withMessages(['assigned_courts' => 'Assign at least one court, without exceeding your total available courts.']);
        }
        $rows = [];
        foreach (PanarottisCalculator::CATEGORIES as $i => $name) {
            $ratio = $i === 0 ? 16 : 4;
            $rows[] = ['name' => $name, 'assigned_courts' => $assigned[$i], 'players_per_court' => $ratio, 'limit' => $assigned[$i] * $ratio];
        }
        return ['model' => 'court-ratio-v2', 'inputs' => ['courts' => (int) $data['courts'], 'assigned_courts' => $assigned],
            'rows' => $rows, 'limits' => array_column($rows, 'limit'), 'assigned_total' => $totalAssigned,
            'unassigned_courts' => (int) $data['courts'] - $totalAssigned, 'suggested_entries' => array_sum(array_column($rows, 'limit'))];
    }
}
