<?php

namespace App\Http\Controllers\Frontend;

use App\Domain\Ranking\Services\RankingReviewCirculationService;
use App\Http\Controllers\Controller;
use App\Models\RankingReviewCampaign;

class RankingReviewController extends Controller
{
    public function show(string $campaign, RankingReviewCirculationService $service)
    {
        $campaign = RankingReviewCampaign::with('series')->where('uuid', $campaign)->firstOrFail();
        abort_if($campaign->status === 'superseded', 410, 'This provisional ranking was replaced. Please use the latest email.');
        if (! $service->snapshotMatches($campaign)) {
            abort(410, 'This provisional ranking was replaced. Please use the latest email.');
        }

        $rankings = $service->runRows($campaign->series, $campaign->run_id);
        abort_if($rankings->isEmpty(), 404);

        return view('frontend.ranking-review', [
            'campaign' => $campaign,
            'series' => $campaign->series,
            'categories' => $rankings->pluck('category')->filter()->unique('id'),
            'rankings' => $rankings,
            'reviewOpen' => now()->lte($campaign->cutoff_at) && ! in_array($campaign->status, ['finalized', 'superseded'], true),
        ]);
    }
}
