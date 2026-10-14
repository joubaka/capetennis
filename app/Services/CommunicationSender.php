<?php

namespace App\Services;

use App\Models\{Event, User};
use Illuminate\Support\Facades\Validator;

class CommunicationSender
{
    /** Defaults for event correspondence; explicit composer fields remain editable. */
    public static function eventDefaults(Event $event, User $actor): array
    {
        $admins = $event->admins()->with('roles')->orderBy('users.id')->get()
            ->filter(fn (User $admin) => filled($admin->name) && filter_var($admin->email, FILTER_VALIDATE_EMAIL));
        $contactEmail = mb_strtolower(trim((string) $event->email));
        $admin = $admins->first(fn (User $candidate) => mb_strtolower(trim($candidate->email)) === $contactEmail)
            ?? $admins->first(fn (User $candidate) => ! $candidate->hasRole('super-user'))
            ?? $admins->first();
        if ($admin) {
            return self::resolve(['from_name' => $admin->name, 'reply_to' => $admin->email], $actor);
        }
        if (filled($event->organizer) && filter_var($event->email, FILTER_VALIDATE_EMAIL)) {
            return self::resolve(['from_name' => $event->organizer, 'reply_to' => $event->email], $actor);
        }

        return self::resolve([], $actor);
    }

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
