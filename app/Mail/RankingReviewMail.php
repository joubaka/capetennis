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
        public ?string $fromAddress = null,
    ) {}

    public function build()
    {
        $this->campaign->loadMissing('series');
        $replyTo = trim((string) $this->campaign->reply_to);

        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('The ranking review reply-to address is invalid.');
        }

        return $this
            ->from($this->fromAddress ?: config('mail.from.address'), 'Cape Tennis')
            ->replyTo($replyTo, 'Cape Tennis Rankings')
            ->subject($this->campaign->subject)
            ->view('emails.ranking-review')
            ->with([
                'reviewUrl' => app(RankingReviewCirculationService::class)->signedPublicUrl($this->campaign),
            ]);
    }
}
