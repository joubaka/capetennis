<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerMergeAudit;
use App\Services\PlayerDuplicateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PlayerDuplicateController extends Controller
{
    public function index(Request $request, PlayerDuplicateService $duplicates): View
    {
        $perPageOption = in_array($request->string('per_page')->toString(), ['25', '50', '100', '200', 'all'], true)
            ? $request->string('per_page')->toString()
            : '25';
        $perPage = $perPageOption === 'all' ? 400 : (int) $perPageOption;
        $mergeFilter = in_array($request->string('merge_filter')->toString(), ['all', 'auto_resolvable', 'ranking_auto'], true)
            ? $request->string('merge_filter')->toString()
            : 'all';

        return view('backend.superadmin.player-duplicates', [
            'candidatePairs' => $duplicates->candidates(
                $perPage,
                $request->boolean('include_reviewed'),
                $mergeFilter
            ),
            'includeReviewed' => $request->boolean('include_reviewed'),
            'perPageOption' => $perPageOption,
            'mergeFilter' => $mergeFilter,
            'recentMerges' => Schema::hasTable('player_merge_audits')
                ? PlayerMergeAudit::with('approvedBy:id,name,email')->latest('merged_at')->limit(10)->get()
                : collect(),
        ]);
    }

    public function review(Request $request, Player $first, Player $second, PlayerDuplicateService $duplicates): View
    {
        $keep = match ($request->integer('keep')) {
            $first->id => $first,
            $second->id => $second,
            default => $duplicates->recommendedKeep($first, $second),
        };
        $remove = $keep->is($first) ? $second : $first;

        return view('backend.superadmin.player-duplicate-review', [
            'analysis' => $duplicates->analyze($keep, $remove),
            'first' => $first,
            'second' => $second,
        ]);
    }

    public function quickReview(Player $first, Player $second, PlayerDuplicateService $duplicates): View
    {
        return view('backend.superadmin.player-duplicate-quick-merge', [
            'analysis' => $duplicates->quickMergeAnalysis($first, $second),
        ]);
    }

    public function bulkReview(Request $request, PlayerDuplicateService $duplicates): View
    {
        $scope = $request->validate([
            'selection_scope' => ['nullable', Rule::in(['page', 'all'])],
        ]);
        if (($scope['selection_scope'] ?? 'page') === 'all') {
            $pairs = $duplicates->allQuickCandidatePairs();
            if ($pairs === []) {
                throw ValidationException::withMessages(['pairs' => 'No unreviewed quick-merge candidates were found.']);
            }
        } else {
            $validated = $request->validate([
                'pairs' => ['required', 'array', 'min:1', 'max:400'],
                'pairs.*' => ['required', 'string', 'distinct', 'regex:/^\d+:\d+$/'],
            ]);
            $pairs = $this->parsePairTokens($validated['pairs']);
        }

        $batch = (($scope['selection_scope'] ?? 'page') === 'all')
            ? $duplicates->quickMergeBatchReview($pairs)
            : $duplicates->plannedMergeBatchReview($pairs);

        return view('backend.superadmin.player-duplicate-bulk-review', compact('batch'));
    }

    public function bulkMerge(Request $request, PlayerDuplicateService $duplicates): RedirectResponse
    {
        $validated = $request->validate([
            'pairs' => ['required', 'array', 'min:1', 'max:400'],
            'pairs.*.first_id' => ['required', 'integer', 'exists:players,id'],
            'pairs.*.second_id' => ['required', 'integer', 'exists:players,id'],
            'batch_digest' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'confirmation' => ['required', 'string', 'max:100'],
            'batch_mode' => ['required', Rule::in(['quick', 'planned'])],
        ]);

        $pairs = collect($validated['pairs'])->map(fn (array $pair) => [
            'first_id' => (int) $pair['first_id'],
            'second_id' => (int) $pair['second_id'],
        ])->values()->all();
        if (collect($pairs)->contains(fn (array $pair) => $pair['first_id'] === $pair['second_id'])) {
            throw ValidationException::withMessages([
                'pairs' => 'Each merge must contain two different player profiles.',
            ]);
        }
        $batch = $validated['batch_mode'] === 'planned'
            ? $duplicates->plannedMergeBatchAnalysis($pairs)
            : $duplicates->quickMergeBatchAnalysis($pairs);
        if (! hash_equals($batch['confirmation_phrase'], trim($validated['confirmation']))) {
            return back()->withInput()->withErrors([
                'confirmation' => "Type exactly: {$batch['confirmation_phrase']}",
            ]);
        }

        $merged = $validated['batch_mode'] === 'planned'
            ? $duplicates->mergePlannedBatch(
                $pairs, $request->user(), $validated['batch_digest'], $validated['reason']
            )
            : $duplicates->mergeQuickBatch(
                $pairs, $request->user(), $validated['batch_digest'], $validated['reason']
            );

        return redirect()->route('superadmin.player-duplicates.index')
            ->with('success', $merged === 1
                ? '1 duplicate profile was merged successfully.'
                : "{$merged} duplicate profiles were merged successfully.");
    }

    public function merge(Request $request, PlayerDuplicateService $duplicates): RedirectResponse
    {
        $validated = $request->validate([
            'keep_player_id' => ['required', 'integer', 'different:remove_player_id', 'exists:players,id'],
            'remove_player_id' => ['required', 'integer', 'exists:players,id'],
            'impact_digest' => ['required', 'string', 'size:64'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'confirmation' => ['required', 'string', 'max:100'],
            'field_sources' => ['nullable', 'array'],
            'field_sources.*' => ['required', Rule::in(['keep', 'remove'])],
        ]);

        $keep = Player::findOrFail($validated['keep_player_id']);
        $remove = Player::findOrFail($validated['remove_player_id']);
        $expectedPhrase = 'MERGE';
        if (! hash_equals($expectedPhrase, trim($validated['confirmation']))) {
            return back()->withInput()->withErrors([
                'confirmation' => "Type exactly: {$expectedPhrase}",
            ]);
        }

        $duplicates->merge(
            $keep,
            $remove,
            $request->user(),
            $validated['field_sources'] ?? [],
            $validated['impact_digest'],
            $validated['reason'],
        );

        Log::info('Player duplicate merge request completed', [
            'kept_player_id' => $keep->id,
            'removed_player_id' => $remove->id,
            'approved_by' => $request->user()->id,
            'redirect_route' => 'superadmin.player-duplicates.index',
        ]);

        return redirect()->route('superadmin.player-duplicates.index')
            ->with('success', "Profile #{$remove->id} was merged into profile #{$keep->id}.");
    }

    public function decision(
        Request $request,
        Player $first,
        Player $second,
        PlayerDuplicateService $duplicates
    ): RedirectResponse {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['not_duplicate', 'review_later'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $duplicates->recordDecision(
            $first,
            $second,
            $request->user(),
            $validated['decision'],
            $validated['reason'],
        );

        return redirect()->route('superadmin.player-duplicates.index')
            ->with('success', 'The duplicate candidate decision was recorded.');
    }

    /** @return array<int, array{first_id:int, second_id:int}> */
    private function parsePairTokens(array $tokens): array
    {
        return collect($tokens)->map(function (string $token) {
            [$firstId, $secondId] = array_map('intval', explode(':', $token, 2));

            return ['first_id' => $firstId, 'second_id' => $secondId];
        })->values()->all();
    }
}
