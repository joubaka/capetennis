<?php

namespace App\Mail;

use App\Domain\Ranking\Services\RankingReviewCirculationService;
use App\Models\RankingReviewCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RankingReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RankingReviewCampaign $campaign,
        public array $playerNames = [],
    ) {}

    public function build()
    {
        $this->campaign->loadMissing('series');

        return $this
            ->from(config('mail.from.address'), 'Cape Tennis')
            ->replyTo($this->campaign->reply_to)
            ->subject($this->campaign->subject)
            ->view('emails.ranking-review')
            ->with([
                'reviewUrl' => app(RankingReviewCirculationService::class)->signedPublicUrl($this->campaign),
            ]);
    }
}
