<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'deadline' => ['nullable', 'integer', 'min:0'],
            'withdrawal_deadline' => ['nullable', 'date'],
            'entryFee' => ['nullable', 'numeric', 'min:0'],
            'eventType' => ['nullable', 'integer'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'information' => ['nullable', 'string'],
            'venue_notes' => ['nullable', 'string'],
            'published' => ['nullable', 'boolean'],
            'signUp' => ['nullable', 'boolean'],
            'series_id' => ['nullable', 'integer', 'exists:series,id'],
        ];
    }
}
