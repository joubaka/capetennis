<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\StandingsService;
use App\Models\{Draw, DrawAuditLog, Registration};
use Illuminate\Support\Facades\DB;

final class LuckyPrizeService
{
    public const ACTION = 'lucky_prize_awarded';

    public function pool(Draw $draw): array
    {
        $blocked = fn (string $message) => ['ready' => false, 'message' => $message, 'players' => [], 'champions' => [], 'previous_winners' => [], 'revision' => null];
        if ($draw->isTeamDraw() || $draw->usesFlexibleMonrad()) return $blocked('Lucky prizes currently support individual singles round-robin and playoff draws.');
        $draw->loadMissing(['settings', 'groups.groupRegistrations.registration.players', 'drawFixtures.fixtureResults']);
        if ($draw->isRoundRobinOnly() && ! $draw->isTwoStageRoundRobin()) {
            $standings = app(RoundRobinGroupResultService::class)->annotate($draw, app(StandingsService::class)->forDraw($draw));
            if ($draw->groups->isEmpty()) return $blocked('Complete the draw and resolve first place before spinning.');
            $championIds = [];
            foreach ($draw->groups as $group) {
                $winner = collect($standings[$group->id] ?? [])->firstWhere('group_winner', true);
                if (! $winner) return $blocked('Complete every group and resolve its first-place winner before spinning.');
                $championIds[] = (int) $winner['reg_id'];
            }
        } else {
            $first = app(DrawFinalPlacementService::class)->forDraw($draw)->firstWhere('position', 1);
            if (($first['status'] ?? null) !== 'resolved') return $blocked('Complete the final and resolve first place before spinning.');
            $championIds = [(int) $first['registration_id']];
        }

        $assigned = $draw->registrations()->pluck('registrations.id')->merge($draw->groups->flatMap->groupRegistrations->pluck('registration_id'))->unique();
        if (collect($championIds)->diff($assigned)->isNotEmpty()) return $blocked('Review the first-place player records in this draw before spinning.');
        $eventChampionIds = Registration::whereIn('id', $championIds)->whereHas('categoryEvents', fn ($query) => $query->where('event_id', $draw->event_id)
            ->when($draw->category_event_id, fn ($category) => $category->where('category_events.id', $draw->category_event_id)))->pluck('id');
        if (collect($championIds)->diff($eventChampionIds)->isNotEmpty()) return $blocked('Review the first-place entry records before spinning.');
        $active = app(GroupAssignmentService::class)->eligible($draw)->whereIn('registration_id', $assigned)->pluck('registration_id')->unique();
        $registrations = Registration::with('players')->whereIn('id', $active->merge($championIds))->get();
        if ($registrations->contains(fn ($registration) => $registration->players->count() !== 1)) return $blocked('Lucky prizes require singles registrations with exactly one player each.');
        $champions = $registrations->whereIn('id', $championIds)->flatMap->players->unique('id')->map(fn ($player) => $this->player($player))->values()->all();
        if ($registrations->whereIn('id', $championIds)->count() !== count(array_unique($championIds))) return $blocked('Review the first-place player records before spinning.');
        $awards = DrawAuditLog::where('draw_id', $draw->id)->where('action', self::ACTION)->orderBy('id')->get();
        $previous = $awards->map(fn ($award) => $award->payload['winner'])->values()->all();
        $excluded = collect($champions)->pluck('id')->merge(collect($previous)->pluck('id'));
        $players = $registrations->whereIn('id', $active)->flatMap->players->unique('id')->reject(fn ($player) => $excluded->contains((int) $player->id))
            ->map(fn ($player) => $this->player($player))->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        return ['ready' => count($players) > 0, 'message' => count($players) ? 'Each eligible player has an equal chance.' : 'No eligible players remain.',
            'players' => $players, 'champions' => $champions, 'previous_winners' => $previous,
            'revision' => hash('sha256', json_encode([$players, $champions, $previous]))];
    }

    public function spin(Draw $draw, string $token, string $revision): array
    {
        return DB::transaction(function () use ($draw, $token, $revision) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $existing = DrawAuditLog::where('draw_id', $draw->id)->where('action', self::ACTION)->get()
                ->first(fn ($audit) => ($audit->payload['token'] ?? null) === $token);
            if ($existing) return $existing->payload['result'];
            $pool = $this->pool($draw);
            abort_unless($pool['ready'], 422, $pool['message']);
            abort_unless(hash_equals($pool['revision'], $revision), 409, 'The eligible players changed. Review the refreshed wheel before spinning.');
            $winner = $pool['players'][random_int(0, count($pool['players']) - 1)];
            $result = ['winner' => $winner, 'players' => $pool['players']];
            DrawAuditLog::record($draw->id, self::ACTION, null, ['token' => $token, 'winner' => $winner, 'revision' => $revision, 'result' => $result]);
            return $result;
        });
    }

    private function player($player): array
    {
        return ['id' => (int) $player->id, 'name' => trim($player->name.' '.$player->surname)];
    }
}
