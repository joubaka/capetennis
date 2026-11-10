<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Services\CavaliersTrialCandidates;
use App\Services\PlayerEligibilityService;
use App\Support\EventTypePresentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CavaliersTrialRosterController extends Controller
{
    public const SELECTED_LIMIT = 32;

    public const RESERVE_LIMIT = 5;

    public function show(Request $request, Event $event, CategoryEvent $categoryEvent): JsonResponse
    {
        $this->authorizeDivision($request, $event, $categoryEvent);

        $draft = $this->draft($categoryEvent)->first();
        $selected = $draft ? json_decode($draft->selected, true) : [];
        $reserves = $draft ? json_decode($draft->reserves, true) : [];
        $players = Player::query()->select('id', 'name', 'surname')
            ->whereIn('id', [...$selected, ...$reserves])->get()->keyBy('id');
        $names = fn (array $ids) => array_map(fn (int $id) => [
            'id' => $id,
            'name' => $players->get($id)?->full_name ?? 'Unavailable player #'.$id,
        ], $ids);

        return response()->json([
            'revision' => $draft?->revision,
            'selected' => $names($selected),
            'reserves' => $names($reserves),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function players(Request $request, Event $event, CategoryEvent $categoryEvent): JsonResponse
    {
        $this->authorizeDivision($request, $event, $categoryEvent);
        $query = $request->validate(['q' => 'required|string|min:2|max:80'])['q'];
        $players = Player::query()->select('id', 'name', 'surname');
        foreach (preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
            $players->where(fn ($match) => $match
                ->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(surname) LIKE LOWER(?) ESCAPE '!'", [$pattern]));
        }

        return response()->json($players->orderBy('surname')->orderBy('name')->orderBy('id')->limit(20)
            ->get()->map(fn (Player $player) => ['id' => $player->id, 'name' => $player->full_name]))
            ->header('Cache-Control', 'private, no-store');
    }

    public function candidates(Request $request, Event $event, CategoryEvent $categoryEvent, CavaliersTrialCandidates $candidates): JsonResponse
    {
        $this->authorizeCandidateSetup($request, $event, $categoryEvent);

        return response()->json($candidates->preview($event, $categoryEvent, $request->user()))
            ->header('Cache-Control', 'private, no-store');
    }

    public function setup(Request $request, Event $event, CategoryEvent $categoryEvent, CavaliersTrialCandidates $candidates): JsonResponse
    {
        $this->authorizeCandidateSetup($request, $event, $categoryEvent);

        return response()->json($candidates->setup($event, $categoryEvent))
            ->header('Cache-Control', 'private, no-store');
    }

    public function updateSetup(Request $request, Event $event, CategoryEvent $categoryEvent, CavaliersTrialCandidates $candidates): JsonResponse
    {
        $this->authorizeCandidateSetup($request, $event, $categoryEvent);
        $regions = array_column($candidates->setup($event, $categoryEvent)['regions'], 'id');
        $input = $request->validate([
            'region_ids' => ['present', 'array', 'list', 'max:100'],
            'region_ids.*' => ['required', 'integer', 'distinct', Rule::in($regions)],
            'excluded_result_region_ids' => ['present', 'array', 'list', 'max:100'],
            'excluded_result_region_ids.*' => ['required', 'integer', 'distinct', Rule::in($regions)],
        ]);
        $setup = array_map(fn (array $ids) => array_map('intval', $ids), $input);

        DB::transaction(function () use ($event, $categoryEvent, $setup) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $settings = $locked->team_result_settings ?? [];
            $settings[CavaliersTrialCandidates::SETUP_KEY][$categoryEvent->id] = $setup;
            $locked->team_result_settings = $settings;
            $locked->save();
        });

        return response()->json($setup)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Event $event, CategoryEvent $categoryEvent, PlayerEligibilityService $eligibility): JsonResponse
    {
        $this->authorizeDivision($request, $event, $categoryEvent);
        $data = $request->validate([
            'revision' => 'present|nullable|uuid',
            'selected' => 'present|array|list|max:'.self::SELECTED_LIMIT,
            'reserves' => 'present|array|list|max:'.self::RESERVE_LIMIT,
            'selected.*' => 'required|integer|min:1',
            'reserves.*' => 'required|integer|min:1',
        ]);
        $selected = array_map('intval', $data['selected']);
        $reserves = array_map('intval', $data['reserves']);
        $ids = [...$selected, ...$reserves];
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['selected' => 'A player can only hold one place in this division.']);
        }

        $conflict = 'This draft changed in another session. Reload it before editing again.';
        $draft = $this->draft($categoryEvent)->first();
        abort_unless($data['revision'] === $draft?->revision, 409, $conflict);
        $existing = $draft ? [...json_decode($draft->selected, true), ...json_decode($draft->reserves, true)] : [];
        $players = Player::query()->select('id', 'name', 'surname')->whereIn('id', $ids)->get()->keyBy('id');
        if ($players->count() !== count($ids)) {
            throw ValidationException::withMessages(['selected' => 'Remove the unavailable player before saving.']);
        }
        foreach (array_diff($ids, $existing) as $playerId) {
            try {
                $eligibility->assertEligible($playerId, $event);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['selected' => $players[$playerId]->full_name.' is currently ineligible for this event.']);
            }
        }

        $revision = (string) Str::uuid();
        $values = [
            'revision' => $revision,
            'selected' => json_encode($selected, JSON_THROW_ON_ERROR),
            'reserves' => json_encode($reserves, JSON_THROW_ON_ERROR),
        ];
        // Compare and swap on the private draft table, including simultaneous first saves.
        $written = $draft
            ? $this->draft($categoryEvent)->where('revision', $data['revision'])->update($values)
            : DB::table('cavaliers_trial_roster_drafts')->insertOrIgnore($values + [
                'event_id' => $event->id,
                'category_event_id' => $categoryEvent->id,
            ]);
        abort_unless($written === 1, 409, $conflict);

        return response()->json(['revision' => $revision]);
    }

    private function authorizeCandidateSetup(Request $request, Event $event, CategoryEvent $categoryEvent): void
    {
        $this->authorizeDivision($request, $event, $categoryEvent);
        abort_unless((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT, 404);
    }

    private function authorizeDivision(Request $request, Event $event, CategoryEvent $categoryEvent): void
    {
        abort_unless(EventTypePresentation::template($event) === 'cavaliers_trials'
            && (int) $categoryEvent->event_id === (int) $event->id, 404);
        $user = $request->user();
        abort_unless($user->hasRole('super-user')
            || ($user->hasRole('admin') && $user->is_event_admin($event->id)), 403);
    }

    private function draft(CategoryEvent $categoryEvent): \Illuminate\Database\Query\Builder
    {
        return DB::table('cavaliers_trial_roster_drafts')
            ->where('event_id', $categoryEvent->event_id)->where('category_event_id', $categoryEvent->id);
    }
}
