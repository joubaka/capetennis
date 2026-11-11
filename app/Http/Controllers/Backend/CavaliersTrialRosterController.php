<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\Player;
use App\Services\CavaliersTrialCandidates;
use App\Services\PlayerEligibilityService;
use App\Support\EventTypePresentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        return response()->json([
            'revision' => $draft?->revision,
            ...$this->rosterPlayers($draft),
            'publication' => $this->publication($categoryEvent),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function exportPdf(Request $request, Event $event, CategoryEvent $categoryEvent, CavaliersTrialCandidates $candidates): \Illuminate\Http\Response
    {
        $this->authorizeCandidateSetup($request, $event, $categoryEvent);
        $draft = $this->draft($categoryEvent)->first();
        abort_unless($draft, 404, 'Save the division draft before exporting.');
        $roster = $this->rosterPlayers($draft);
        $preview = $candidates->preview($event, $categoryEvent, $request->user());
        $evidence = collect($preview['candidates'])->keyBy('id');
        $rostered = collect($roster)->collapse()->pluck('id')->flip();
        $leftOut = $evidence->where('source', 'Inter-Districts')->except($rostered->keys()->all());

        return Pdf::loadView('backend.nominations.cavaliers-roster-pdf', compact('event', 'categoryEvent', 'roster', 'preview', 'evidence', 'leftOut'))
            ->setPaper('a4')->download('cavaliers-trials-'.Str::slug($categoryEvent->category->name).'.pdf')
            ->header('Cache-Control', 'private, no-store');
    }

    private function rosterPlayers(?\stdClass $draft): array
    {
        $selected = $draft ? json_decode($draft->selected, true) : [];
        $reserves = $draft ? json_decode($draft->reserves, true) : [];
        $declined = json_decode($draft?->declined ?? '[]', true);
        $players = Player::query()->select('id', 'name', 'surname')
            ->whereIn('id', [...$selected, ...$reserves, ...$declined])->get()->keyBy('id');
        $names = fn (array $ids) => array_map(fn (int $id) => [
            'id' => $id,
            'name' => $players->get($id)?->full_name ?? 'Unavailable player #'.$id,
        ], $ids);

        return [
            'selected' => $names($selected),
            'reserves' => $names($reserves),
            'declined' => $names($declined),
        ];
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
            'declined' => 'sometimes|array|list|max:256',
            'declined.*' => 'required|integer|min:1',
        ]);
        $selected = array_map('intval', $data['selected']);
        $reserves = array_map('intval', $data['reserves']);
        $draft = $this->draft($categoryEvent)->first();
        $previousDeclines = json_decode($draft?->declined ?? '[]', true);
        $declined = array_map('intval', $data['declined'] ?? $previousDeclines);
        $ids = [...$selected, ...$reserves, ...$declined];
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['selected' => 'A player can only hold one place in this division.']);
        }

        $conflict = 'This draft changed in another session. Reload it before editing again.';
        abort_unless($data['revision'] === $draft?->revision, 409, $conflict);
        $existing = $draft ? [...json_decode($draft->selected, true), ...json_decode($draft->reserves, true)] : [];
        $players = Player::query()->select('id', 'name', 'surname')->whereIn('id', $ids)->get()->keyBy('id');
        if ($players->count() !== count($ids)) {
            throw ValidationException::withMessages(['selected' => 'Remove the unavailable player before saving.']);
        }
        foreach (array_diff([...$selected, ...$reserves], $existing) as $playerId) {
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
            'declined' => json_encode($declined, JSON_THROW_ON_ERROR),
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

    public function transferNominations(Request $request, Event $event, CategoryEvent $categoryEvent, PlayerEligibilityService $eligibility): JsonResponse
    {
        $this->authorizeDivision($request, $event, $categoryEvent);
        $input = $request->validate([
            'publish' => 'required|boolean',
            'revision' => 'required_if:publish,true|nullable|uuid',
            'fingerprint' => 'required|string|size:64',
        ]);
        $publish = (bool) $input['publish'];

        $publication = DB::transaction(function () use ($event, $categoryEvent, $input, $publish, $eligibility) {
            $draft = $this->draft($categoryEvent)->lockForUpdate()->first();
            $division = CategoryEvent::query()->whereKey($categoryEvent->id)->where('event_id', $event->id)
                ->lockForUpdate()->firstOrFail();
            $existing = $division->nominations()->orderBy('id')->lockForUpdate()->get();
            abort_unless($this->fingerprint($division, $existing) === $input['fingerprint'], 409,
                'The event nominations changed. Reload the division and review the list again.');
            if (! $publish) {
                $division->update(['nominations_published' => false]);

                return $this->publication($division);
            }

            abort_unless($draft && $draft->revision === ($input['revision'] ?? null), 409,
                'The saved draft changed. Reload it before publishing.');
            $selected = json_decode($draft->selected, true, flags: JSON_THROW_ON_ERROR);
            abort_if($selected === [], 422, 'Select and save at least one player before publishing.');
            abort_if($existing->contains(fn (EventNomination $nomination) => (int) $nomination->event_id !== (int) $event->id),
                422, 'A nomination belongs to a different event. Review this division before publishing.');

            $players = Player::query()->whereIn('id', $selected)->get(['id', 'name', 'surname'])->keyBy('id');
            abort_unless($players->count() === count($selected), 422, 'Remove unavailable players from the draft before publishing.');
            foreach ($selected as $playerId) {
                try {
                    $eligibility->assertEligible($players[$playerId], $event);
                } catch (QueryException $exception) {
                    throw $exception;
                } catch (RuntimeException) {
                    throw ValidationException::withMessages(['selected' => $players[$playerId]->full_name.' is currently ineligible for this event.']);
                }
            }

            $retained = $existing->whereIn('player_id', $selected)->keyBy('player_id');
            $remove = $existing->diff($retained);
            abort_if(\App\Models\InterprovincialTrialInvitation::query()->whereIn('nomination_id', $remove->modelKeys())->exists(),
                422, 'A nomination to remove is linked to an invitation. Review it before publishing.');
            foreach ($remove as $nomination) {
                $nomination->delete();
            }
            foreach ($selected as $index => $playerId) {
                $nomination = $retained->get($playerId) ?? new EventNomination([
                    'event_id' => $event->id,
                    'category_event_id' => $categoryEvent->id,
                    'player_id' => $playerId,
                ]);
                $nomination->roster_position = $index + 1;
                $nomination->save();
            }
            $division->update(['nominations_published' => true]);

            return $this->publication($division);
        });

        return response()->json($publication)->header('Cache-Control', 'private, no-store');
    }

    private function publication(CategoryEvent $division): array
    {
        $nominations = $division->nominations()->orderBy('id')->get();
        $visible = $nominations->filter(fn (EventNomination $nomination) =>
            (int) $nomination->event_id === (int) $division->event_id);
        $visible->load('player:id,name,surname');

        return [
            'published' => (bool) $division->nominations_published,
            'fingerprint' => $this->fingerprint($division, $nominations),
            'players' => $visible->sortBy(fn (EventNomination $nomination) => $nomination->roster_position ?? PHP_INT_MAX)
                ->map(fn (EventNomination $nomination) => ['id' => $nomination->player_id, 'name' => $nomination->display_name])
                ->values()->all(),
        ];
    }

    private function fingerprint(CategoryEvent $division, Collection $nominations): string
    {
        return hash('sha256', json_encode([(bool) $division->nominations_published,
            $nominations->map->only(['id', 'event_id', 'player_id', 'roster_position'])->values()->all(),
        ], JSON_THROW_ON_ERROR));
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
