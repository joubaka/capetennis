<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Ranking\Services\RankingReviewCirculationService;
use App\Http\Controllers\Controller;
use App\Mail\RankingReviewMail;
use App\Models\RankingReviewCampaign;
use App\Models\Series;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RankingReviewCirculationController extends Controller
{
    public function preview(Request $request, Series $series, RankingReviewCirculationService $service)
    {
        $this->authorize('update', $series);

        $preview = $service->preview($series);
        $defaults = $preview['defaults'];
        $preview['defaults'] = [
            'uuid' => $defaults['uuid'],
            'cutoff_at' => $defaults['cutoff_at']->toIso8601String(),
            'cutoff_input' => $defaults['cutoff_at']->format('Y-m-d\TH:i'),
            'subject' => $defaults['subject'],
            'message' => $defaults['message'],
            'reply_to' => $this->defaultReplyTo($request->user()),
        ];

        return response()->json($preview);
    }

    public function emailPreview(Request $request, Series $series, RankingReviewCirculationService $service)
    {
        $this->authorize('update', $series);
        $data = $this->validateMessage($request);
        $campaign = new RankingReviewCampaign([
            'uuid' => $data['uuid'],
            'series_id' => $series->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'reply_to' => $data['reply_to'],
            'cutoff_at' => Carbon::parse($data['cutoff_at'], config('app.timezone')),
        ]);
        $campaign->setRelation('series', $series);

        $recipientNames = $service->preview($series)['audience']['recipients'][0]['player_names'] ?? ['Player / Parent'];

        return response()->json([
            'html' => (new RankingReviewMail($campaign, $recipientNames))->render(),
            'preview_recipient' => implode(' / ', $recipientNames),
        ]);
    }

    public function send(Request $request, Series $series, RankingReviewCirculationService $service)
    {
        $this->authorize('update', $series);
        $data = $this->validateMessage($request);
        $cutoff = Carbon::parse($data['cutoff_at'], config('app.timezone'));
        if ($cutoff->gt(now()->addDays(30))) {
            throw ValidationException::withMessages(['cutoff_at' => 'The reply cutoff must be within 30 days.']);
        }

        $campaign = $service->send(
            $series,
            $request->user(),
            $data['uuid'],
            $data['subject'],
            $data['message'],
            $data['reply_to'],
            $cutoff,
        );

        return response()->json([
            'message' => "Ranking review queued for {$campaign->recipient_count} unique recipients.",
            'campaign' => $service->deliveryReport($campaign),
        ]);
    }

    public function status(Series $series, RankingReviewCampaign $campaign, RankingReviewCirculationService $service)
    {
        $this->authorize('view', $series);
        abort_unless((int) $campaign->series_id === (int) $series->id, 404);

        return response()->json($service->deliveryReport($campaign));
    }

    public function retry(Request $request, Series $series, RankingReviewCampaign $campaign, RankingReviewCirculationService $service)
    {
        $this->authorize('update', $series);
        abort_unless((int) $campaign->series_id === (int) $series->id, 404);
        $count = $service->retryFailed($campaign, $request->user());

        return response()->json([
            'message' => $count ? "{$count} failed emails queued again." : 'There are no failed emails to retry.',
            'campaign' => $service->deliveryReport($campaign),
        ]);
    }

    private function validateMessage(Request $request): array
    {
        return $request->validate([
            'uuid' => ['required', 'uuid'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'reply_to' => ['required', 'email:rfc', 'max:255'],
            'cutoff_at' => ['required', 'date', 'after:now'],
        ]);
    }

    private function defaultReplyTo($user): string
    {
        return filter_var($user?->email, FILTER_VALIDATE_EMAIL)
            ? $user->email
            : 'info@capetennis.co.za';
    }
}
