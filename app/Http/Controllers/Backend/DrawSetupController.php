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
        'playoffs' => ['Playoffs only', 'Start with a knockout bracket. Winners advance; no placement matches.'],
        'monrad' => ['Monrad only', 'Start everyone in the opening round. Winners and losers play for finishing positions.'],
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
            'challenge_playoffs' => ['sometimes', 'boolean'],
            'challenge_pairs' => [Rule::requiredIf(fn () => $request->input('workflow') === 'feed_in_monrad' && $request->boolean('challenge_playoffs')), 'nullable', 'array', 'min:1', 'max:31'],
            'challenge_pairs.*' => ['integer', 'distinct', Rule::in(range(2, 62, 2))],
        ]);
        if (! $draw->category_event_id && ! in_array($data['workflow'], ['round_robin', 'round_robin_playoffs'], true) && empty($data['category_event_id'])) {
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
            if ($sameWorkflow && $sameChallenges && $currentPairs === $challengePairs) return;
            $preserveStarts = in_array($draw->settings?->workflow, ['monrad', 'feed_in_monrad'], true)
                && in_array($data['workflow'], ['monrad', 'feed_in_monrad'], true);
            $retainedDraft = $preserveStarts ? $draw->flexibleMonrad?->draft : null;
            $isRoundRobinPlayoffUpgrade = in_array($draw->settings?->workflow, [null, 'round_robin'], true)
                && $data['workflow'] === 'round_robin_playoffs'
                && ! $draw->drawFixtures()->where('stage', '!=', 'RR')->exists()
                && empty($draw->flexibleMonrad?->draft['slots'])
                && ! $draw->flexibleMonrad?->graph;
            $hasExistingFormatState = $draw->drawFixtures()->exists() || $draw->groups()->exists()
                || ! empty($draw->flexibleMonrad?->draft['slots']) || ! empty($draw->flexibleMonrad?->graph);
            $resetCounts = null;
            if (! $isRoundRobinPlayoffUpgrade && $hasExistingFormatState) {
                abort_unless((bool) ($data['reset_existing'] ?? false), 409,
                    'Confirm that changing format will remove this draw’s fixtures, scheduled times and group placements.');
                $resetCounts = $resetter->reset($draw);
                $draw->unsetRelation('flexibleMonrad');
            }
            if (! $draw->category_event_id && ! in_array($data['workflow'], ['round_robin', 'round_robin_playoffs'], true)) {
                abort_if($draw->registrations()->reorder()->exists(), 409, 'Choose a new empty draw before changing its player category.');
                $draw->update(['category_event_id' => $data['category_event_id']]);
            }
            $settings = ['workflow' => $data['workflow']];
            if ($resetCounts) {
                $settings += ['boxes' => null, 'playoff_size' => null, 'playoff_config' => null, 'preset_key' => null];
            }
            if ($data['workflow'] === 'feed_in_monrad') {
                $settings['playoff_config'] = array_replace($resetCounts ? [] : ($draw->settings?->playoff_config ?? []),
                    ['challenge_playoffs' => $challengePlayoffs, 'challenge_pairs' => $challengePairs]);
            }
            $draw->settings()->updateOrCreate(['draw_id' => $draw->id], $settings);
            if (in_array($data['workflow'], ['round_robin', 'round_robin_playoffs'], true)) {
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
        return redirect()->route('backend.draw.roundrobin.show', $draw);
    }
}
