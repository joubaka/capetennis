<?php

namespace App\Mail;

use App\Models\PlusDigestDelivery;
use Illuminate\Mail\Mailable;

class PlusMorningDigestMail extends Mailable
{
    public function __construct(public PlusDigestDelivery $morningDigestDelivery) {}

    public function subjectLine(): string
    {
        $count = collect($this->morningDigestDelivery->items)->where('kind', 'new_event')->count();
        return $count ? $count.' new '.($count === 1 ? 'event' : 'events').' added - Cape Tennis Plus' : 'Your Cape Tennis Plus morning updates';
    }

    public function body(): string
    {
        return view('emails.plus-morning-digest', ['items' => $this->morningDigestDelivery->items])->render();
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
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo(config('mail.from.address'), config('mail.from.name'))->subject($this->subjectLine())->html($this->body());
    }
}
