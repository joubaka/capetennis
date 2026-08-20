<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BulkLookupJtaPlayersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'string', 'max:100'],
            'players' => ['required', 'array', 'min:1', 'max:50'],
            'players.*.client_reference' => ['required', 'integer', 'distinct'],
            'players.*.first_name' => ['required', 'string', 'max:100'],
            'players.*.last_name' => ['required', 'string', 'max:100'],
            'players.*.date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
