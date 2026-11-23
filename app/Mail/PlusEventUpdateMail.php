<?php
namespace App\Mail;
use App\Models\{Event, PlusEventUpdateDelivery, PlusEventUpdateState};
use Illuminate\Mail\Mailable;

class PlusEventUpdateMail extends Mailable
{
    public Event $event;
    public function __construct(public PlusEventUpdateDelivery $eventUpdateDelivery)
    {
        $this->event = Event::findOrFail(PlusEventUpdateState::findOrFail($eventUpdateDelivery->state_id)->event_id);
    }
    public function subjectLine(): string { return 'Event update - '.$this->event->name; }
    public function body(): string
    {
        return view('emails.plus-event-update', ['event' => $this->event, 'delivery' => $this->eventUpdateDelivery])->render();
    }
    public function canonicalHtml(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ((new \DOMXPath($document))->query('//text()[normalize-space(.)=""]') as $node) $node->parentNode->removeChild($node);
        return trim($document->saveHTML());
    }
    public function build(): self
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))->replyTo(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->subjectLine())->html($this->body());
    }
}
