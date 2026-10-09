<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Category, CategoryEvent, Draw, Fixture, Registration};
use App\Services\Performance\{PlayerAbilityRefreshState, PlayerRatingBadgeService};
use Illuminate\Http\Request;

class PlayerRatingBadgeController extends Controller
{
    public function __invoke(Request $request, PlayerRatingBadgeService $badges, PlayerAbilityRefreshState $state)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $data = $request->validate([
            'players' => ['sometimes', 'array', 'max:100'], 'players.*' => ['integer', 'min:1'],
            'registrations' => ['sometimes', 'array', 'max:100'], 'registrations.*' => ['integer', 'min:1'],
            'fixtures' => ['sometimes', 'array', 'max:100'], 'fixtures.*' => ['integer', 'min:1'],
            'draw_id' => ['nullable', 'integer', 'min:1', 'prohibits:category_event_id,category_id'],
            'category_event_id' => ['nullable', 'integer', 'min:1', 'prohibits:draw_id,category_id'],
            'category_id' => ['nullable', 'integer', 'min:1', 'prohibits:draw_id,category_event_id'],
        ]);
        $status = $state->status();
        $context = isset($data['category_event_id']) ? CategoryEvent::with(['category', 'event'])->findOrFail($data['category_event_id'])
            : (isset($data['draw_id']) ? Draw::with(['categoryEvent.category', 'categoryEvent.event'])->findOrFail($data['draw_id'])
                : (isset($data['category_id']) ? Category::findOrFail($data['category_id']) : null));
        $ratings = [];
        foreach (array_unique($data['players'] ?? []) as $id) { $ratings['p:'.$id] = array_values(array_filter([$badges->forPlayer((int) $id, $context)])); }
        foreach (Registration::with(['players' => fn ($query) => $query->limit(3)])->whereIn('id', array_unique($data['registrations'] ?? []))->get() as $registration) {
            if ($registration->players->count() > 2) { $ratings['r:'.$registration->id] = []; continue; }
            $ratings['r:'.$registration->id] = $registration->players->map(function ($player) use ($badges, $context) {
                $rating = $badges->forPlayer($player->id, $context);
                return $rating ? array_merge($rating, ['title' => $player->full_name.' · '.$rating['title']]) : null;
            })->filter()->values()->all();
        }
        foreach (Fixture::with(['draw.categoryEvent.category', 'draw.event',
            'registration1.players' => fn ($query) => $query->limit(3), 'registration2.players' => fn ($query) => $query->limit(3)])
            ->whereIn('id', array_unique($data['fixtures'] ?? []))->get() as $fixture) {
            foreach ([1, 2] as $side) {
                $players = $fixture->{'registration'.$side}?->players ?? collect();
                $ratings['f:'.$fixture->id.':'.$side] = !$fixture->draw || ($context instanceof Draw && (int) $context->id !== (int) $fixture->draw_id)
                    || ($context instanceof CategoryEvent && (int) $context->id !== (int) $fixture->draw->category_event_id)
                    || ($context instanceof Category && (int) $context->id !== (int) $fixture->draw->categoryEvent?->category_id) || $players->count() > 2 ? []
                    : $players->map(fn ($player) => $badges->forPlayer($player->id, $fixture->draw))->filter()->values()->all();
            }
        }
        return response()->json(['ratings' => $ratings, 'status' => $status])->header('Cache-Control', 'private, no-store');
    }
}
