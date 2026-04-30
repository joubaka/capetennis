<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'category_event_id' => ['required', 'integer', 'exists:category_events,id'],
            'player_ids' => ['required', 'array', 'min:1'],
            'player_ids.*' => ['integer', 'exists:players,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'An event must be selected.',
            'event_id.exists' => 'The selected event does not exist.',
            'category_event_id.required' => 'A category must be selected.',
            'category_event_id.exists' => 'The selected category does not exist.',
            'player_ids.required' => 'At least one player must be selected.',
            'player_ids.min' => 'At least one player must be selected.',
            'player_ids.*.exists' => 'One or more selected players do not exist.',
        ];
    }
}
