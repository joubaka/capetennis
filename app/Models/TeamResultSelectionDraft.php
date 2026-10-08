<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamResultSelectionDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['region_ids' => 'array', 'excluded_result_region_ids' => 'array', 'formats' => 'array', 'selected_keys' => 'array',
            'reasons' => 'array', 'snapshot' => 'array', 'version' => 'integer'];
    }
}
