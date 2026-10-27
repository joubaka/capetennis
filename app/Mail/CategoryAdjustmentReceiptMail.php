<?php

namespace App\Mail;

use App\Models\CategoryAdjustment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\RateLimited;

class CategoryAdjustmentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public \App\Models\Event $event;

    public function __construct(public CategoryAdjustment $adjustment)
    {
        $this->event = $adjustment->event;
    }

    public function build(): self
    {
        return $this->subject('Category change receipt – '.$this->adjustment->event->name)
            ->markdown('emails.category-adjustment-receipt');
    }

    public function middleware(): array
    {
        return [new RateLimited('outbound-mail')];
    }
}
