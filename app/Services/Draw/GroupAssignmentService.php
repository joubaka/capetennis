<?php

namespace App\Services\Draw;

use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class GroupAssignmentService
{
    /** Compare saved group membership with existing RR fixtures without changing either. */
    public function fixturesNeedRegeneration(Draw $draw, ?\Illuminate\Support\Collection $fixtures = null, ?\Illuminate\Support\Collection $groups = null): bool
    {
        $fixtures ??= $draw->drawFixtures()->where('stage', 'RR')->get(['draw_group_id', 'registration1_id', 'registration2_id']);
        if ($fixtures->isEmpty()) return false;

        $groups ??= $draw->groups()->with('groupRegistrations')->get();
        if ($groups->isEmpty()) return false;
        $expected = [];
        foreach ($groups as $group) {
            $ids = $group->groupRegistrations->pluck('registration_id')->filter()->map(fn ($id) => (int) $id)->sort()->values();
            foreach ($ids as $index => $first) {
                foreach ($ids->slice($index + 1) as $second) {
                    $expected[] = $group->id.':'.$first.':'.$second;
                }
            }
        }
        $actual = $fixtures->map(function ($fixture) {
            $pair = [(int) $fixture->registration1_id, (int) $fixture->registration2_id];
            sort($pair);
            return $fixture->draw_group_id.':'.implode(':', $pair);
        })->all();
        sort($expected);
        sort($actual);

        return $expected !== $actual;
    }

    public function eligible(Draw $draw): Builder
    {
        return CategoryEventRegistration::query()
            ->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $draw->event_id))
            ->when($draw->category_event_id, fn ($query) => $query->where('category_event_id', $draw->category_event_id))
            ->where('payment_status_id', 1)
            ->whereNull('withdrawn_at')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'not like', '%withdrawn%'))
            ->whereHas('registration.players');
    }

    public function revision(Draw $draw): string
    {
        $groups = $draw->groups()->where('round_robin_phase', 1)->orderBy('id')->get()->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
            'players' => $group->groupRegistrations()->get(['registration_id', 'seed'])->toArray(),
        ])->toArray();

        return hash('sha256', json_encode($groups));
    }

    public function save(Draw $draw, array $input): array
    {
        $data = Validator::make($input, [
            'groups' => 'present|array',
            'groups.*.group_id' => 'required|integer|distinct',
            'groups.*.registration_ids' => 'present|array',
            'groups.*.registration_ids.*' => 'required|integer',
            'revision' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($draw, $data) {
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            abort_if($draw->isTwoStageRoundRobin() && $draw->groups()->where('round_robin_phase', 2)->exists(), 409,
                'Group membership is frozen after progression to the second round robin.');
            abort_if($draw->locked || $draw->published, 403, 'This draw cannot be rearranged.');
            $revision = $this->revision($draw);
            abort_if(isset($data['revision']) && ! hash_equals($revision, $data['revision']), 409,
                'Assignments changed in another session. Reload the roster before saving.');

            $groupIds = collect($data['groups'])->pluck('group_id')->map(fn ($id) => (int) $id);
            if ($draw->groups()->whereIn('id', $groupIds)->count() !== $groupIds->count()) {
                throw ValidationException::withMessages(['groups' => 'Every group must belong to this draw.']);
            }
            $ids = collect($data['groups'])->flatMap(fn ($group) => $group['registration_ids'])->map(fn ($id) => (int) $id);
            $untouched = DB::table('draw_group_registrations')->whereIn('draw_group_id',
                $draw->groups()->whereNotIn('id', $groupIds)->select('id'))->pluck('registration_id');
            if ($ids->unique()->count() !== $ids->count() || $ids->intersect($untouched)->isNotEmpty()) {
                throw ValidationException::withMessages(['groups' => 'A player can only appear in one group.']);
            }
            $eligible = $this->eligible($draw)->whereIn('registration_id', $ids)->pluck('registration_id')->unique();
            if ($ids->diff($eligible)->isNotEmpty()) {
                throw ValidationException::withMessages(['groups' => 'Choose paid, active players from this draw’s event and category.']);
            }

            $changed = collect($data['groups'])->contains(function ($group) use ($draw) {
                $current = $draw->groups()->findOrFail($group['group_id'])->groupRegistrations()->pluck('registration_id')->map(fn ($id) => (int) $id)->all();
                return $current !== array_map('intval', $group['registration_ids']);
            });
            if ($changed && $draw->drawFixtures()->whereHas('fixtureResults')->exists()) {
                throw ValidationException::withMessages(['groups' => 'Players cannot be rearranged after results have been recorded.']);
            }
            if ($changed) {
                foreach ($data['groups'] as $group) {
                    $links = [];
                    foreach ($group['registration_ids'] as $index => $id) {
                        $links[$id] = ['seed' => $index + 1];
                    }
                    $draw->groups()->findOrFail($group['group_id'])->registrations()->sync($links);
                }
            }
            DrawAuditLog::record($draw->id, 'groups_saved', null, ['groups_processed' => $groupIds->count()]);

            return ['success' => true, 'status' => 'ok', 'groups_processed' => $groupIds->count(),
                'revision' => $this->revision($draw), 'fixtures_need_regeneration' => $changed && $draw->drawFixtures()->exists()];
        });
    }
}
