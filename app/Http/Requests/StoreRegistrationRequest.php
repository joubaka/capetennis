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
            'custom_int3'    => ['required', 'integer', 'exists:events,id'],
            'player'         => ['required', 'array', 'min:1'],
            'player.*'       => ['required', 'integer', 'exists:players,id'],
            'category'       => ['required', 'array', 'min:1'],
            'category.*'     => ['required', 'integer', 'exists:category_events,id'],
            'terms_accepted' => ['required', 'in:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'custom_int3.required'    => 'An event must be selected.',
            'custom_int3.exists'      => 'The selected event does not exist.',
            'player.required'         => 'At least one player must be selected.',
            'player.min'              => 'At least one player must be selected.',
            'player.*.required'       => 'Please select a valid player.',
            'player.*.exists'         => 'One or more selected players do not exist.',
            'category.required'       => 'At least one category must be selected.',
            'category.min'            => 'At least one category must be selected.',
            'category.*.required'     => 'Please select a valid category.',
            'category.*.exists'       => 'One or more selected categories do not exist.',
            'terms_accepted.required' => 'You must accept the terms and conditions and Code of Conduct before proceeding.',
            'terms_accepted.in'       => 'You must accept the terms and conditions and Code of Conduct before proceeding.',
        ];
    }
}
