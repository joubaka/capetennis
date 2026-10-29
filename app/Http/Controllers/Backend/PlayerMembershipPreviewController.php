<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Performance\PlayerMembershipPreviewService;
use App\Services\Performance\{PlayerMembershipHistoryService, PlayerMembershipComparisonService};
use App\Services\Performance\PlayerMembershipProfileFreshnessService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PlayerMembershipPreviewController extends Controller
{
    public function alerts(Request $request)
    {
        $this->guard($request);
        return $this->response('alerts', ['reason' => null, 'selected' => '', 'search' => '', 'as_of' => null, 'built_at' => null]);
    }

    public function detail(Request $request, string $player, PlayerMembershipPreviewService $service, PlayerMembershipHistoryService $history)
    {
        $this->guard($request);
        $settings = $request->validate(['cohort' => ['required', Rule::in(array_keys(PlayerMembershipPreviewService::COHORTS))], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $data = $service->build($settings['cohort']);
        $selectedPlayer = null; $evidence = ['available' => false, 'reason' => null, 'players' => []];
        if (!$data['reason']) {
            $selectedPlayer = collect($data['rows'])->first(fn ($row) => $row['id'] === (int) $player);
            abort_unless($selectedPlayer && ctype_digit($player), 404);
            $selectedPlayer = app(PlayerMembershipProfileFreshnessService::class)->enrich([$selectedPlayer])[0];
            $evidence = $history->load($settings['cohort'], [$selectedPlayer['id']], $data['snapshot_version']);
        }
        $matches = $evidence['players'][$selectedPlayer['id'] ?? 0]['matches'] ?? [];
        $historyPage = $this->historyPage($request, $matches, 'page', ['cohort' => $settings['cohort']]);
        return $this->response('detail', $data + compact('selectedPlayer', 'evidence', 'historyPage'));
    }

    public function compare(Request $request, PlayerMembershipPreviewService $service, PlayerMembershipHistoryService $history, PlayerMembershipComparisonService $comparison)
    {
        $this->guard($request);
        $settings = $request->validate(['cohort' => ['nullable', Rule::in(array_keys(PlayerMembershipPreviewService::COHORTS))],
            'first' => ['nullable', 'integer', 'min:1', 'required_with:second'], 'second' => ['nullable', 'integer', 'min:1', 'required_with:first', 'different:first'],
            'first_page' => ['nullable', 'integer', 'min:1', 'max:10000'], 'second_page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        if ((!empty($settings['first']) || !empty($settings['second'])) && empty($settings['cohort'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cohort' => 'Choose a recorded age group before selecting players.']);
        }
        $data = $service->build($settings['cohort'] ?? '');
        $options = !$data['reason'] && !empty($settings['cohort']) ? $data['rows'] : [];
        $firstPlayer = null; $secondPlayer = null; $assessment = null;
        $evidence = ['available' => false, 'reason' => null, 'players' => []];
        if (!$data['reason'] && !empty($settings['first']) && !empty($settings['second'])) {
            $firstPlayer = collect($options)->firstWhere('id', (int) $settings['first']);
            $secondPlayer = collect($options)->firstWhere('id', (int) $settings['second']);
            abort_unless($firstPlayer && $secondPlayer, 404);
            [$firstPlayer, $secondPlayer] = app(PlayerMembershipProfileFreshnessService::class)->enrich([$firstPlayer, $secondPlayer]);
            $evidence = $history->load($settings['cohort'], [$firstPlayer['id'], $secondPlayer['id']], $data['snapshot_version']);
            $assessment = $comparison->assess($firstPlayer, $secondPlayer, $evidence);
        }
        $query = array_filter(['cohort' => $settings['cohort'] ?? '', 'first' => $firstPlayer['id'] ?? null, 'second' => $secondPlayer['id'] ?? null], fn ($v) => $v !== null && $v !== '');
        $firstHistory = $this->historyPage($request, $evidence['players'][$firstPlayer['id'] ?? 0]['matches'] ?? [], 'first_page', $query + ['second_page' => $settings['second_page'] ?? 1]);
        $secondHistory = $this->historyPage($request, $evidence['players'][$secondPlayer['id'] ?? 0]['matches'] ?? [], 'second_page', $query + ['first_page' => $settings['first_page'] ?? 1]);
        return $this->response('compare', $data + compact('options', 'firstPlayer', 'secondPlayer', 'assessment', 'evidence', 'firstHistory', 'secondHistory'));
    }

    public function directory(Request $request, PlayerMembershipPreviewService $service)
    {
        return $this->show($request, $service, 'directory');
    }

    public function leaderboard(Request $request, PlayerMembershipPreviewService $service)
    {
        return $this->show($request, $service, 'leaderboard');
    }

    public function membership(Request $request, PlayerMembershipPreviewService $service)
    {
        return $this->show($request, $service, 'membership');
    }

    private function show(Request $request, PlayerMembershipPreviewService $service, string $page)
    {
        $this->guard($request);
        $settings = $request->validate(['cohort' => ['nullable', Rule::in(array_keys(PlayerMembershipPreviewService::COHORTS))],
            'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $data = $service->build($settings['cohort'] ?? '', trim($settings['search'] ?? ''));
        foreach (['rows' => 'players', 'active' => 'activePlayers'] as $source => $target) {
            $number = (int) ($settings['page'] ?? 1);
            $visible = array_slice($data[$source], ($number - 1) * 25, 25);
            if (($page === 'directory' && $source === 'rows') || ($page === 'leaderboard' && $source === 'active')) {
                $visible = app(PlayerMembershipProfileFreshnessService::class)->enrich($visible);
            }
            $data[$target] = new LengthAwarePaginator($visible, count($data[$source]), 25, $number,
                ['path' => $request->url(), 'query' => array_filter(['cohort' => $data['selected'], 'search' => $data['search']], fn ($value) => $value !== '')]);
        }
        return $this->response($page, $data);
    }

    private function guard(Request $request): void
    {
        abort_unless(app()->environment('local'), 404);
        abort_unless($request->user()?->hasRole('super-user'), 403);
    }

    private function historyPage(Request $request, array $matches, string $name, array $query): LengthAwarePaginator
    {
        $page = (int) $request->input($name, 1);
        return new LengthAwarePaginator(array_slice($matches, ($page - 1) * 20, 20), count($matches), 20, $page,
            ['path' => $request->url(), 'query' => $query, 'pageName' => $name]);
    }

    private function response(string $page, array $data)
    {
        return response()->view('backend.player-performance.membership-preview.'.$page, $data + ['page' => $page])
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
