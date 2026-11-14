<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, DrawAuditLog};
use App\Services\Draw\{DrawFormatResetService, FlexibleMonradService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DrawSetupController extends Controller
{
    public const OPTIONS = [
        'round_robin' => ['Round robin only', 'Everyone plays within their group. Final standings decide the result; there are no playoffs.'],
        'round_robin_playoffs' => ['Round robin → playoffs', 'Build groups first, then choose how players advance to playoffs.'],
        'round_robin_round_robin' => ['Round robin → round robin', 'Play in groups, then choose equal-position groups or top-four playoffs with one lower round robin.'],
        'playoffs' => ['Knockout — Playoffs only', 'Winners advance; losing players are eliminated. No placement matches.'],
        'monrad' => ['Play for every position — Monrad only', 'Winners and losers continue for finishing positions. With eight players, play out positions 1–8.'],
        'feed_in_monrad' => ['Feed-in Monrad', 'Start everyone in the opening round. Earlier-round losers play through a feed-in draw against later-round losers, with matches for every finishing position.'],
        'custom_monrad' => ['Custom Monrad', 'Place players in different starting rounds, then play for finishing positions.'],
    ];

    public function show(Draw $draw)
    {
        $this->authorize('view', $draw);
        $fixtureIds = $draw->drawFixtures()->pluck('id');
        $flexible = $draw->flexibleMonrad;
        $placedCount = count(array_filter($flexible?->draft['slots'] ?? [], fn ($slot) => ($slot['type'] ?? null) === 'player'));
        $fieldCount = $placedCount ?: app(FlexibleMonradService::class)->eligible($draw)->count();
        $pairLimit = min(64, $fieldCount ?: ($flexible?->draft['size'] ?? 32));
        $config = $draw->settings?->playoff_config ?? [];
        $hasFormatState = $fixtureIds->isNotEmpty() || $draw->groups()->exists()
            || ! empty($flexible?->draft['slots']) || ! empty($flexible?->graph);
        return view('backend.draw.setup', [
            'draw' => $draw,
            'options' => self::OPTIONS,
            'challengePairLimit' => $pairLimit,
            'selectedChallengePairs' => $config['challenge_pairs'] ?? (($config['challenge_playoffs'] ?? false)
                ? array_values(array_filter(range(2, 62, 2), fn ($upper) => $upper < $pairLimit)) : []),
            'resetSummary' => [
                'fixtures' => $fixtureIds->count(),
                'scheduled_times' => DB::table('order_of_plays')->where('draw_id', $draw->id)
                    ->orWhereIn('fixture_id', $fixtureIds)->count(),
                'groups' => $draw->groups()->count(),
                'results' => DB::table('fixture_results')->whereIn('fixture_id', $fixtureIds)->count(),
                'roster_entries' => $draw->registrations()->reorder()->count(),
                'has_format_state' => $hasFormatState,
            ],
        ]);
    }

    public function store(Request $request, Draw $draw, DrawFormatResetService $resetter)
    {
        $this->authorize('update', $draw);
        $data = $request->validate([
            'workflow' => 'required|in:'.implode(',', array_keys(self::OPTIONS)),
            'category_event_id' => ['nullable', 'integer', Rule::exists('category_events', 'id')->where('event_id', $draw->event_id)],
            'reset_existing' => ['nullable', 'boolean'],
            'groups' => ['nullable', 'integer', 'min:1', 'max:26'],
            'second_stage_format' => ['sometimes', Rule::in(['equal_positions', \App\Services\Draw\HybridRoundRobinService::FORMAT])],
            'challenge_playoffs' => ['sometimes', 'boolean'],
            'challenge_pairs' => [Rule::requiredIf(fn () => $request->input('workflow') === 'feed_in_monrad' && $request->boolean('challenge_playoffs')), 'nullable', 'array', 'min:1', 'max:31'],
            'challenge_pairs.*' => ['integer', 'distinct', Rule::in(range(2, 62, 2))],
        ]);
        if (! $draw->category_event_id && ! in_array($data['workflow'], ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true) && empty($data['category_event_id'])) {
            return view('backend.draw.setup-category', [
                'draw' => $draw, 'workflow' => $data['workflow'], 'label' => self::OPTIONS[$data['workflow']][0],
                'challengePlayoffs' => $data['workflow'] === 'feed_in_monrad' && (bool) ($data['challenge_playoffs'] ?? false),
                'challengePairs' => $data['challenge_pairs'] ?? [],
                'categories' => \App\Models\CategoryEvent::where('event_id', $draw->event_id)
                    ->with('category')
                    ->withCount(['categoryEventRegistrations as eligible_draw_entries_count' => fn ($entries) => $entries
                        ->where('payment_status_id', 1)->whereNull('withdrawn_at')
                        ->where(fn ($status) => $status->whereNull('status')->orWhere('status', 'not like', '%withdrawn%'))
                        ->whereHas('registration.players')])
                    ->orderBy('ordering')->get(),
            ]);
        }
        DB::transaction(function () use ($draw, $data, $resetter) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked || $draw->published || $draw->oop_published, 409,
                'Unlock and unpublish the draw and its schedule before choosing a format.');
            abort_if($draw->isTeamDraw(), 422, 'Use the team draw setup for team fixtures.');
            $challengePlayoffs = $data['workflow'] === 'feed_in_monrad' && (bool) ($data['challenge_playoffs'] ?? false);
            $sameWorkflow = $draw->settings?->workflow === $data['workflow'];
            $roundRobin = in_array($data['workflow'], ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true);
            $groupCount = $roundRobin && isset($data['groups']) ? (int) $data['groups'] : null;
            $secondStageFormat = $data['workflow'] === 'round_robin_round_robin'
                ? ($data['second_stage_format'] ?? ($draw->hasHybridRoundRobin() ? \App\Services\Draw\HybridRoundRobinService::FORMAT : 'equal_positions')) : null;
            $hybrid = $secondStageFormat === \App\Services\Draw\HybridRoundRobinService::FORMAT;
            $sameSecondStage = $draw->hasHybridRoundRobin() === $hybrid;
            abort_if($hybrid && ($groupCount ?? $draw->groups()->where('round_robin_phase', 1)->count()) !== 2, 422,
                'The top-four playoffs and lower round robin format requires exactly two groups.');
            if (!$sameSecondStage && $sameWorkflow) {
                abort_if($draw->groups()->where('round_robin_phase', 2)->exists()
                    || $draw->drawFixtures()->where('stage', '!=', 'RR')->exists(), 409,
                    'Choose the second-stage format before preparing later-stage groups or fixtures. Existing fixtures and bookings cannot be replaced by this option.');
            }
            if ($hybrid && !$sameWorkflow) {
                abort_if($draw->groups()->where('round_robin_phase', 2)->exists()
                    || $draw->drawFixtures()->where('stage', '!=', 'RR')->exists(), 409,
                    'Choose this format in a new draw before preparing later-stage fixtures. Existing fixtures and bookings remain protected.');
            }
            $groupsChanged = $groupCount !== null && $draw->groups()->when($draw->isTwoStageRoundRobin(), fn ($query) => $query->where('round_robin_phase', 1))->count() !== $groupCount;
            $currentChallenges = $draw->settings?->workflow === 'feed_in_monrad' && (bool) ($draw->settings?->playoff_config['challenge_playoffs'] ?? false);
            $challengePairs = $challengePlayoffs ? array_map('intval', $data['challenge_pairs'] ?? []) : [];
            sort($challengePairs);
            $fieldCount = count($draw->flexibleMonrad?->graph['positions'] ?? [])
                ?: count(array_filter($draw->flexibleMonrad?->draft['slots'] ?? [], fn ($slot) => ($slot['type'] ?? null) === 'player'));
            $eligibilityDraw = clone $draw;
            if (! $eligibilityDraw->category_event_id && isset($data['category_event_id'])) $eligibilityDraw->category_event_id = $data['category_event_id'];
            $fieldCount = $fieldCount ?: app(FlexibleMonradService::class)->eligible($eligibilityDraw)->count();
            $legacyPairs = array_values(array_filter(range(2, 62, 2), fn ($upper) => $upper < $fieldCount));
            $currentPairs = $currentChallenges ? array_map('intval', $draw->settings?->playoff_config['challenge_pairs'] ?? $legacyPairs) : [];
            sort($currentPairs);
            if ($challengePlayoffs && $fieldCount > 0 && ! array_filter($challengePairs, fn ($upper) => $upper + 1 <= $fieldCount)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['challenge_pairs' => 'Select at least one challenge pair present in this field.']);
            }
            $sameChallenges = $currentChallenges === $challengePlayoffs;
            if ($sameWorkflow && $sameSecondStage && $sameChallenges && $currentPairs === $challengePairs && ! $groupsChanged) return;
            abort_if($draw->event?->hasRecordedResults(), 409,
                'Draw format and group rules cannot change after the first tournament result has been recorded.');
            $preserveStarts = in_array($draw->settings?->workflow, ['monrad', 'feed_in_monrad'], true)
                && in_array($data['workflow'], ['monrad', 'feed_in_monrad'], true);
            $retainedDraft = $preserveStarts ? $draw->flexibleMonrad?->draft : null;
            $isSafeRoundRobinChange = in_array($draw->settings?->workflow, [null, 'round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true)
                && in_array($data['workflow'], ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true)
                && ! $draw->groups()->where('round_robin_phase', 2)->exists()
                && ! $draw->drawFixtures()->where('stage', '!=', 'RR')->exists()
                && empty($draw->flexibleMonrad?->draft['slots'])
                && ! $draw->flexibleMonrad?->graph;
            $hasExistingFormatState = $draw->drawFixtures()->exists() || $draw->groups()->exists()
                || ! empty($draw->flexibleMonrad?->draft['slots']) || ! empty($draw->flexibleMonrad?->graph);
            $resetCounts = null;
            if ((! $isSafeRoundRobinChange || $groupsChanged) && $hasExistingFormatState) {
                abort_unless((bool) ($data['reset_existing'] ?? false), 409,
                    'Confirm that changing format will remove this draw’s fixtures, scheduled times and group placements.');
                $resetCounts = $resetter->reset($draw);
                $draw->unsetRelation('flexibleMonrad');
            }
            if (! $draw->category_event_id && ! in_array($data['workflow'], ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true)) {
                abort_if($draw->registrations()->reorder()->exists(), 409, 'Choose a new empty draw before changing its player category.');
                $draw->update(['category_event_id' => $data['category_event_id']]);
            }
            $settings = ['workflow' => $data['workflow']];
            if ($resetCounts) {
                $settings += ['boxes' => null, 'playoff_size' => null, 'playoff_config' => null, 'preset_key' => null];
            }
            if ($data['workflow'] === 'round_robin_round_robin' && (!$sameWorkflow || !$sameSecondStage || $resetCounts)) {
                $settings['playoff_config'] = $hybrid ? [
                    '_second_round_robin' => ['state' => 'selected', 'format' => \App\Services\Draw\HybridRoundRobinService::FORMAT, 'carried_fixture_ids' => []],
                    'main' => ['enabled' => true, 'slug' => 'MAIN', 'name' => 'Positions 1–4', 'size' => 4, 'positions' => [1, 2]],
                ] : [];
            }
            if ($groupCount !== null) {
                $settings['boxes'] = $groupCount;
                if ($data['workflow'] === 'round_robin_playoffs' && ($groupsChanged || ! $sameWorkflow || $resetCounts)) {
                    // One qualifier per group (top two for a single group) gives
                    // a bracket that fits every group, including non-powers of two.
                    $qualifiers = max(2, $groupCount);
                    $size = collect([2, 4, 8, 16, 32])->first(fn ($size) => $size >= $qualifiers);
                    $settings['playoff_config'] = [[
                        'name' => 'Championship', 'slug' => 'main', 'size' => $size,
                        'positions' => $groupCount === 1 ? [1, 2] : [1], 'enabled' => true,
                    ]];
                    $settings['playoff_size'] = $size;
                    $settings['preset_key'] = null;
                }
                for ($index = 0; $index < $groupCount; $index++) {
                    $draw->groups()->firstOrCreate(['name' => chr(65 + $index)]);
                }
            }
            if ($data['workflow'] === 'feed_in_monrad') {
                $settings['playoff_config'] = array_replace($resetCounts ? [] : ($draw->settings?->playoff_config ?? []),
                    ['challenge_playoffs' => $challengePlayoffs, 'challenge_pairs' => $challengePairs]);
            }
            $draw->settings()->updateOrCreate(['draw_id' => $draw->id], $settings);
            if (in_array($data['workflow'], ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true)) {
                $draw->flexibleMonrad()->delete(); // Only an empty, ungenerated draft can reach here.
                $draw->settings()->update(['draw_format_id' => \App\Models\DrawFormats::where('name', 'Round Robin')->value('id')]);
            } else {
                $eligibleCount = app(FlexibleMonradService::class)->eligible($draw->fresh())->count();
                $suggestedSize = collect([4, 8, 16, 32, 64])->first(fn ($size) => $size >= max(2, $eligibleCount)) ?? 64;
                if ($retainedDraft && $resetCounts) {
                    // Only server-saved starts survive. Normal eligibility and
                    // withdrawal validation still run in the canonical service.
                    \App\Models\FlexibleMonradDraw::create(['draw_id' => $draw->id, 'draft' => $retainedDraft, 'revision' => 0]);
                    $draw->unsetRelation('flexibleMonrad');
                }
                app(FlexibleMonradService::class)->save($draw->fresh(), $retainedDraft ?? ['size' => $suggestedSize, 'slots' => []], $draw->flexibleMonrad?->revision ?? 0);
            }
            DrawAuditLog::record($draw->id, 'workflow_selected', null, $data + ['reset_counts' => $resetCounts]);
        });
        $destination = route('backend.draw.roundrobin.show', $draw);
        if (in_array($data['workflow'], ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true)) {
            $destination .= '#settings';
        }

        return redirect()->to($destination);
    }
}
