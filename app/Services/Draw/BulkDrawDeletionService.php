<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Event, Fixture, TeamFixture, User, Venue};
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Validation\ValidationException;

final class BulkDrawDeletionService
{
    public function review(Event $event, User $actor, array $ids, ?string $revision = null): array
    {
        return DB::transaction(function () use ($event, $actor, $ids, $revision) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('event.manage', $event);
            $ids = array_map('intval', $ids); sort($ids);
            $draws = Draw::withoutEagerLoads()->where('event_id', $event->id)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $this->ensure(count($ids) > 0 && count($ids) <= 200 && count(array_unique($ids)) === count($ids) && $draws->count() === count($ids), 'Select up to 200 distinct draws belonging to this event.');
            foreach ($draws as $draw) Gate::forUser($actor)->authorize('delete', $draw);
            $this->ensure(!$draws->contains(fn ($draw) => $draw->locked || $draw->published), 'Locked or published draws cannot be deleted.');
            $individual = Fixture::withoutEagerLoads()->whereIn('draw_id', $ids)->orderBy('id')->lockForUpdate()->get();
            $team = TeamFixture::withoutEagerLoads()->whereIn('draw_id', $ids)->orderBy('id')->lockForUpdate()->get();
            $ties = DB::table('team_ties')->whereIn('draw_id', $ids)->orderBy('id')->lockForUpdate()->get();
            $this->ensure(!$draws->contains(fn ($draw) => $draw->oop_published)
                && !DB::table('published_schedule_assignments')->where(fn ($query) => $query->whereIn('draw_id', $ids)
                    ->orWhere(fn ($rows) => $rows->where('fixture_kind', 'team')->whereIn('fixture_id', $team->modelKeys()))
                    ->orWhere(fn ($rows) => $rows->where('fixture_kind', 'individual')->whereIn('fixture_id', $individual->modelKeys())))->exists(), 'Hide published match times before deleting draws.');
            $this->ensure(!$individual->contains(fn ($fixture) => (int) $fixture->match_status !== 0 || (int) $fixture->winner_registration > 0)
                && !$team->contains(fn ($fixture) => (int) $fixture->match_status !== 0)
                && !DB::table('fixture_results')->whereIn('fixture_id', $individual->modelKeys())->exists()
                && !DB::table('team_fixture_results')->whereIn('team_fixture_id', $team->modelKeys())->exists()
                && !$ties->contains(fn ($tie) => in_array($tie->status, ['published', 'in_progress', 'completed'], true) || $tie->published_at || $tie->winner_team_id), 'Published, started or scored ties cannot be deleted.');
            $allDraws = Draw::withoutEagerLoads()->where('event_id', $event->id)->orderBy('id')->get();
            foreach ($allDraws as $candidate) {
                $competition = $candidate->team_draw_selection['competition'] ?? [];
                if (in_array($candidate->id, $ids, true)) $this->ensure(!$competition, 'A selected draw belongs to a linked placement programme.');
                foreach (['source_draw_id', 'draft_playoff_draw_id', 'playoff_draw_id'] as $key) {
                    $this->ensure(!in_array((int) ($competition[$key] ?? 0), $ids, true), 'A selected draw is referenced by a placement programme.');
                }
            }
            $this->ensure(!Draw::whereNotIn('id', $ids)->where(function ($query) use ($ids) {
                foreach (['source_draw_id', 'draft_playoff_draw_id', 'playoff_draw_id'] as $key) $query->orWhereIn('team_draw_selection->competition->'.$key, $ids);
            })->exists(), 'Another programme depends on a selected draw.');
            $this->ensure(!DB::table('draw_events')->whereIn('draw_id', $ids)->where('event_id', '!=', $event->id)->exists(), 'A selected draw is shared with another event.');
            $this->ensure(!Fixture::whereNotIn('draw_id', $ids)->where(fn ($query) => $query->whereIn('parent_fixture_id', $individual->modelKeys())->orWhereIn('loser_parent_fixture_id', $individual->modelKeys()))->exists(), 'Other draws depend on a selected draw.');
            $this->ensure(!DB::table('team_substitutions')->where('event_id', $event->id)->exists(), 'This event has competition substitution history. Preserve its draw history.');
            $state = [
                'event_draws' => $allDraws->map->getAttributes()->all(),
                'individual' => $individual->map->getAttributes()->all(), 'team' => $team->map->getAttributes()->all(), 'ties' => $ties->all(),
                'players' => DB::table('team_fixture_players')->whereIn('team_fixture_id', $team->modelKeys())->orderBy('id')->lockForUpdate()->get()->all(),
                'individual_bookings' => DB::table('order_of_plays')->where(fn ($query) => $query->whereIn('draw_id', $ids)->orWhereIn('fixture_id', $individual->modelKeys()))->orderBy('id')->lockForUpdate()->get()->all(),
                'team_bookings' => DB::table('team_order_of_plays')->whereIn('team_fixture_id', $team->modelKeys())->orderBy('id')->lockForUpdate()->get()->all(),
                'draft' => DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->lockForUpdate()->first(),
            ];
            foreach (['draw_groups', 'draw_registrations', 'draw_teams', 'draw_venues', 'draw_settings', 'flexible_monrad_draws', 'draw_events', 'sub_draws', 'schedules', 'draw_venue_court_allocations', 'schedule_publication_baselines'] as $table) {
                $state[$table] = $this->rows($table, 'draw_id', $ids);
            }
            $state['schedules'] = DB::table('schedules')->where(fn ($query) => $query->whereIn('draw_id', $ids)->orWhereIn('fixture_id', $individual->modelKeys()))->orderBy('id')->lockForUpdate()->get()->all();
            $groupIds = collect($state['draw_groups'])->pluck('id')->all();
            foreach (['draw_group_registrations', 'draw_group_rankings'] as $table) $state[$table] = $this->rows($table, 'draw_group_id', $groupIds);
            $state['registration_sub_draws'] = $this->rows('registration_sub_draws', 'sub_draw_id', collect($state['sub_draws'])->pluck('id')->all());
            if (Schema::hasColumn('rank_venue_mappings', 'draw_id')) $state['rank_venue_mappings'] = $this->rows('rank_venue_mappings', 'draw_id', $ids);
            $this->ensure(!collect($state['individual_bookings'])->contains(fn ($booking) => ($booking->draw_id && !in_array((int) $booking->draw_id, $ids, true))
                || ($booking->fixture_id && !in_array((int) $booking->fixture_id, $individual->modelKeys(), true))), 'A selected draw has inconsistent booking references. Review it before deletion.');
            $this->ensure(!collect($state['schedules'])->contains(fn ($booking) => ($booking->draw_id && !in_array((int) $booking->draw_id, $ids, true))
                || ($booking->fixture_id && !in_array((int) $booking->fixture_id, $individual->modelKeys(), true))), 'A selected draw has inconsistent schedule references. Review it before deletion.');
            $token = hash_hmac('sha256', json_encode([$event->id, $actor->id, $ids, $state], JSON_THROW_ON_ERROR), (string) config('app.key'));
            if ($revision !== null) {
                $this->ensure(hash_equals($token, $revision), 'The draws or their fixtures changed. Review the selection again.');
                foreach ($draws as $draw) $this->deleteGraph($draw);
                if ($state['draft']) {
                    $options = json_decode($state['draft']->options, true, flags: JSON_THROW_ON_ERROR);
                    $fixtureKeys = array_merge($individual->map(fn ($fixture) => 'individual:'.$fixture->id)->all(), $team->map(fn ($fixture) => 'team:'.$fixture->id)->all());
                    $options = $this->pruneDraft($options, $ids, $fixtureKeys);
                    DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->update(['options' => json_encode($options, JSON_THROW_ON_ERROR), 'updated_by' => $actor->id, 'updated_at' => now()]);
                }
            }
            return ['success' => true, 'event_id' => (int) $event->id, 'revision' => $token,
                'draws' => $draws->map(fn ($draw) => ['id' => (int) $draw->id, 'name' => $draw->drawName])->all(),
                'individual_fixtures' => $individual->count(), 'team_fixtures' => $team->count(), 'deleted' => $revision !== null];
        });
    }

    /** Shared graph cleanup; callers own authorization, lifecycle checks and the transaction. */
    public function deleteGraph(Draw $draw): array
    {
        $individualIds = Fixture::where('draw_id', $draw->id)->pluck('id');
        $teamIds = TeamFixture::where('draw_id', $draw->id)->pluck('id');
        DrawAuditLog::record($draw->id, 'deleted', null, ['event_id' => $draw->event_id, 'name' => $draw->drawName,
            'individual_fixtures' => $individualIds->count(), 'team_fixtures' => $teamIds->count()]);
        DB::table('order_of_plays')->whereIn('fixture_id', $individualIds)->delete();
        DB::table('schedules')->whereIn('fixture_id', $individualIds)->delete();
        foreach (['order_of_plays', 'schedules'] as $table) DB::table($table)->where('draw_id', $draw->id)->delete();
        DB::table('fixture_results')->whereIn('fixture_id', $individualIds)->delete();
        foreach (['team_fixture_results', 'team_fixture_players', 'team_order_of_plays'] as $table) DB::table($table)->whereIn('team_fixture_id', $teamIds)->delete();
        TeamFixture::whereIn('id', $teamIds)->delete();
        Fixture::whereIn('id', $individualIds)->delete();
        $groups = DB::table('draw_groups')->where('draw_id', $draw->id)->pluck('id');
        foreach (['draw_group_registrations', 'draw_group_rankings'] as $table) DB::table($table)->whereIn('draw_group_id', $groups)->delete();
        $subDraws = DB::table('sub_draws')->where('draw_id', $draw->id)->pluck('id');
        DB::table('registration_sub_draws')->whereIn('sub_draw_id', $subDraws)->delete();
        DB::table('sub_draws')->whereIn('id', $subDraws)->delete();
        foreach (['draw_groups', 'draw_registrations', 'draw_teams', 'draw_venues', 'draw_settings', 'flexible_monrad_draws', 'draw_events', 'team_ties', 'draw_venue_court_allocations', 'schedule_publication_baselines'] as $table) DB::table($table)->where('draw_id', $draw->id)->delete();
        if (Schema::hasColumn('rank_venue_mappings', 'draw_id')) DB::table('rank_venue_mappings')->where('draw_id', $draw->id)->delete();
        $draw->delete();
        return [$individualIds->count(), $teamIds->count()];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['draw_ids' => $message]);
    }

    private function rows(string $table, string $column, array $ids): array
    {
        return DB::table($table)->whereIn($column, $ids)->lockForUpdate()->get()->sortBy(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR))->values()->all();
    }

    private function pruneDraft(array $options, array $ids, array $fixtureKeys): array
    {
        $list = array_is_list($options);
        foreach ($options as $key => $value) {
            if (in_array($key, $fixtureKeys, true)) { unset($options[$key]); continue; }
            if (!is_array($value)) continue;
            if (isset($value['fixture_key']) && in_array($value['fixture_key'], $fixtureKeys, true)) { unset($options[$key]); continue; }
            if (isset($value['draw_id']) || isset($value['drawId'])) {
                if (in_array((int) ($value['draw_id'] ?? $value['drawId']), $ids, true)) { unset($options[$key]); continue; }
            }
            if (in_array($key, ['selected_fixture_keys', 'preview_frozen_keys'], true)) $options[$key] = array_values(array_diff($value, $fixtureKeys));
            elseif (in_array($key, ['draw_ids', 'draw_order'], true)) $options[$key] = array_values(array_filter($value, fn ($id) => !in_array((int) $id, $ids, true)));
            elseif ($key === 'individual_programme_by_draw') $options[$key] = array_diff_key($value, array_flip($ids));
            else $options[$key] = $this->pruneDraft($value, $ids, $fixtureKeys);
        }
        return $list ? array_values($options) : $options;
    }
}
