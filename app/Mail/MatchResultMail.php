<?php

namespace App\Mail;

use App\Models\MatchResultNotification;
use App\Services\MatchResultNotificationService;
use Illuminate\Mail\Mailable;

class MatchResultMail extends Mailable
{
    public \App\Models\Event $event;
    public function __construct(public MatchResultNotification $resultNotification)
    {
        $this->event = \App\Models\Event::findOrFail($resultNotification->event_id);
    }
    public function build(): self
    {
        $service = app(MatchResultNotificationService::class);
        $replyTo = $service->replyTo($this->resultNotification);
        foreach ($replyTo as $email) $this->replyTo($email);
        return $this->subject($this->subjectLine())->html($this->body($replyTo));
    }
    public function subjectLine(): string
    {
        return ($this->resultNotification->revision > 1 ? 'Corrected match result' : 'New match result').' - '.$this->resultNotification->snapshot['event'];
    }
    public function body(array $replyTo): string
    {
        return view('emails.match-result', [
            'notification' => $this->resultNotification,
            'replyTo' => $replyTo,
            'linkedParentNames' => app(MatchResultNotificationService::class)->linkedParentNames($this->resultNotification),
        ])->render();
    }

    public function canonicalHtml(string $html): string
    {
        // Mail rendering can normalize HTML entities and the doctype newline.
        // Compare the complete parsed document, retaining text and attributes.
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        return trim($document->saveHTML());
    }
}
