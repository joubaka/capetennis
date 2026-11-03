<?php

namespace App\Mail;

use App\Models\{Draw, Event, Player, PlusPublicationDelivery, PlusPublicationState};
use Illuminate\Mail\Mailable;

class PlusPublicationMail extends Mailable
{
    public Event $event;

    public function __construct(public PlusPublicationDelivery $publicationDelivery)
    {
        $state = PlusPublicationState::findOrFail($publicationDelivery->state_id);
        $this->event = Event::findOrFail($state->event_id);
    }

    public function subjectLine(): string
    {
        $changes = $this->publicationDelivery->changes;
        return ($changes['draw'] ? 'Draw published' : (($changes['schedule_initial'] ?? false) ? 'Schedule published' : 'Schedule updated')).' - '.$this->event->name;
    }

    public function body(): string
    {
        $state = PlusPublicationState::findOrFail($this->publicationDelivery->state_id);
        $draw = Draw::findOrFail($state->draw_id);
        $player = Player::findOrFail($this->publicationDelivery->player_id);
        return view('emails.plus-publication', [
            'eventName' => $this->event->name, 'drawName' => $draw->drawName, 'playerName' => $player->full_name,
            'drawPublished' => (bool) $this->publicationDelivery->changes['draw'], 'scheduleChanged' => (bool) $this->publicationDelivery->changes['fixtures'],
            'scheduleInitial' => (bool) ($this->publicationDelivery->changes['schedule_initial'] ?? false),
            'profileUrl' => route('my.tennis', ['player' => $player->id]),
            'publicUrl' => route($draw->isTeamDraw() ? 'frontend.fixtures.show'
                : ($draw->usesFlexibleMonrad() ? 'public.flexible-monrad.show' : 'public.roundrobin.show'), $draw->id),
        ])->render();
    }

    public function build(): self
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->subjectLine())->html($this->body());
    }

    public function canonicalHtml(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        // Laravel's HTML renderer reformats element-only whitespace. Preserve all text and attributes.
        foreach ((new \DOMXPath($document))->query('//text()[normalize-space(.)=""]') as $node) $node->parentNode->removeChild($node);
        return trim($document->saveHTML());
    }
}
