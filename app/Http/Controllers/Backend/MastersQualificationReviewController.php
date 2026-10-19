<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Performance\MastersQualificationReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MastersQualificationReviewController extends Controller
{
    public function index(Request $request, MastersQualificationReviewService $service)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $input = $request->validate([
            'cohort' => ['nullable', Rule::in(MastersQualificationReviewService::DIVISIONS)],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before:2100-01-01'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $data = $service->build($input['cohort'] ?? 'u10 boys', CarbonImmutable::parse($input['date'] ?? '2026-12-07', 'Africa/Johannesburg'), (int) ($input['page'] ?? 1));
        return response()->view('backend.player-performance.masters-qualification-review', $data)->header('Cache-Control', 'no-store, private');
    }
}
