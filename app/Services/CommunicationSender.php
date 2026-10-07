<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CommunicationSender
{
    public static function resolve(array $input, User $actor): array
    {
        return Validator::make([
            'from_name' => $input['from_name'] ?? $input['fromName'] ?? $actor->name,
            'reply_to' => $input['reply_to'] ?? $input['replyTo'] ?? $actor->email,
        ], [
            'from_name' => ['required', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'reply_to' => ['required', 'string', 'email:rfc', 'max:255', 'not_regex:/[\r\n]/'],
        ])->validate();
    }
}
