<?php

namespace App\Services\InterprovincialTrials;

use App\Models\Event;

final class RegistrationMessage
{
    public function draft(Event $event): array
    {
        $open = (bool) $event->published && $event->registrationIsOpen();
        $instructions = $open
            ? 'The event registration window is open. Please visit the event page and find your player. If your player is listed for registration, choose Register and complete registration and payment.'
            : 'Registration is currently closed. Please visit the event page for the nominated players and event information.';
        $body = "Dear players and parents,\n\nCongratulations on being nominated for {$event->name}!\n\n{$instructions}";

        return [
            'subject' => $event->name.' — Registration information',
            'body' => $body."\n\nKind regards,\nCape Tennis",
        ];
    }

    public function communicationDraft(Event $event): array
    {
        $draft = $this->draft($event);
        $draft['subject'] = $event->name.' — Registration information';
        $draft['body'] = str_replace("Congratulations on being nominated for {$event->name}!", "Please find the registration information for {$event->name} below.", $draft['body']);
        $draft['body'] = str_replace('for the nominated players and event information.', 'for player and event information.', $draft['body']);
        $draft['body'] = str_replace("\n\nKind regards,", "\n\n{players}\n\nFee: R{fee}\n\nView event: {url}\n\nKind regards,", $draft['body']);

        return $draft;
    }
}
