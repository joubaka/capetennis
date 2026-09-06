<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, DrawFormats, Fixture, FlexibleMonradDraw, Registration};
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class FlexibleMonradService
{
    public function eligible(Draw $draw)
    {
        return $this->activeEntries($draw)
            ->whereDoesntHave('draws', fn ($q) => $q->where('draws.category_event_id', $draw->category_event_id)
                ->where('draws.id', '!=', $draw->id));
    }

    private function activeEntries(Draw $draw)
    {
        // Category membership is authoritative even if an old draw pivot still exists.
        return Registration::with('players')->whereHas('categoryEvents', fn ($q) => $q
            ->where('category_events.id', $draw->category_event_id)
            ->where('category_events.event_id', $draw->event_id)
            ->whereNotIn('category_event_registrations.status', [
                'withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded',
                'refund_requested', 'refunded', 'cancelled',
            ])
            ->where('category_event_registrations.payment_status_id', 1)
            ->whereNull('category_event_registrations.withdrawn_at')
            ->whereNull('category_event_registrations.deleted_at'));
    }

    public function save(Draw $draw, array $draft, int $revision): FlexibleMonradDraw
    {
        return DB::transaction(function () use ($draw, $draft, $revision) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $this->editable($draw);
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->first();
            $this->revision($record, $revision);
            abort_if($record?->graph || $draw->drawFixtures()->exists(), 409, 'Fixtures already exist. Use an empty draw for the placement editor.');
            $draft = $this->validateDraft($draw, $draft);
            $record ??= new FlexibleMonradDraw(['draw_id' => $draw->id]);
            $record->fill(['draft' => $draft, 'revision' => $revision + 1])->save();
            $format = DrawFormats::where('name', 'Flexible Monrad')->firstOrFail();
            $draw->settings()->updateOrCreate(['draw_id' => $draw->id], ['draw_format_id' => $format->id]);
            DrawAuditLog::record($draw->id, 'monrad_draft_saved', null, ['revision' => $record->revision, 'draft' => $draft]);
            return $record;
        });
    }

    public function generate(Draw $draw, int $revision): FlexibleMonradDraw
    {
        return DB::transaction(function () use ($draw, $revision) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $this->editable($draw);
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->firstOrFail();
            $this->revision($record, $revision);
            if ($record->graph) return $record;
            abort_if($draw->drawFixtures()->exists(), 409, 'This draw already contains fixtures.');
            $draft = $this->validateDraft($draw, $record->draft);
            $graph = app(FlexibleMonradCompiler::class)->compile($draft);
            $map = [];
            foreach ($graph['nodes'] as $key => $node) {
                $fixture = Fixture::create(['draw_id' => $draw->id, 'stage' => 'FM',
                    'round' => $node['round'], 'match_nr' => count($map) + 1, 'match_status' => 0]);
                $map[$key] = $fixture->id;
            }
            // Also populate the standard links for existing fixture consumers.
            foreach ($graph['nodes'] as $key => $node) {
                foreach ($node['sources'] as $slot => $source) {
                    if (! isset($source['match'])) continue;
                    $from = Fixture::findOrFail($map[$source['match']]);
                    $from->{$source['type'] === 'winner' ? 'parent_fixture_id' : 'loser_parent_fixture_id'} = $map[$key];
                    if ($source['type'] === 'winner') $from->feeder_slot = $slot + 1;
                    $from->save();
                }
            }
            $draw->registrations()->sync($graph['players']);
            $record->fill(['graph' => $graph, 'fixture_map' => $map, 'revision' => $revision + 1])->save();
            $this->resolve($record);
            DrawAuditLog::record($draw->id, 'monrad_generated', null, ['revision' => $record->revision, 'matches' => count($map)]);
            return $record;
        });
    }

    public function publish(Draw $draw, int $revision, bool $published): FlexibleMonradDraw
    {
        return DB::transaction(function () use ($draw, $revision, $published) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 409, 'The draw is locked.');
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->firstOrFail();
            $this->revision($record, $revision);
            abort_if($published && ! in_array($draw->settings?->workflow, ['playoffs', 'monrad', 'custom_monrad'], true),
                422, 'Select a draw format before publishing.');
            abort_unless($record->graph, 422, 'Generate and review the fixtures first.');
            $this->resolve($record);
            $draw->update(['published' => $published]);
            $record->increment('revision');
            DrawAuditLog::record($draw->id, $published ? 'monrad_published' : 'monrad_unpublished');
            return $record->refresh();
        });
    }

    public function reopen(Draw $draw, int $revision): FlexibleMonradDraw
    {
        return DB::transaction(function () use ($draw, $revision) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $this->editable($draw);
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->firstOrFail();
            $this->revision($record, $revision);
            $fixtures = $draw->drawFixtures()->where('stage', 'FM');
            abort_if((clone $fixtures)->whereHas('fixtureResults')->exists(), 409, 'This draw has results. Starting positions cannot be reopened.');
            $ids = $fixtures->pluck('id');
            abort_if(DB::table('order_of_plays')->whereIn('fixture_id', $ids)->exists()
                || DB::table('schedules')->where('draw_id', $draw->id)->exists(), 409,
                'Remove the existing schedule before reopening starting positions.');
            $fixtures->delete();
            $record->fill(['graph' => null, 'fixture_map' => null, 'revision' => $revision + 1])->save();
            DrawAuditLog::record($draw->id, 'monrad_draft_reopened', null, ['revision' => $record->revision]);
            return $record;
        });
    }

    /**
     * Reopen a withdrawal-affected draw while retaining an auditable copy of
     * its complete schedule footprint. Other draws and bookings are untouched.
     *
     * @return array{record: FlexibleMonradDraw, schedule_count: int}
     */
    public function prepareWithdrawalRedraw(Draw $draw, int $revision): array
    {
        return DB::transaction(function () use ($draw, $revision) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 409, 'Unlock the draw before preparing a withdrawal redraw.');

            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->lockForUpdate()->firstOrFail();
            $this->revision($record, $revision);
            abort_unless($record->graph, 422, 'Generate fixtures before preparing a withdrawal redraw.');

            $fixtures = $draw->drawFixtures()->where('stage', 'FM');
            abort_if((clone $fixtures)->whereHas('fixtureResults')->exists(), 409,
                'This draw has results. Use withdrawal walkovers so completed history is preserved.');

            $withdrawn = $this->withdrawn($draw, $record->graph);
            abort_unless($withdrawn, 422, 'This draw has no withdrawn players to remove.');

            $publishedBefore = (bool) $draw->published;
            $schedulePublishedBefore = (bool) $draw->oop_published;
            $draftBefore = $record->draft;

            $fixtureIds = $fixtures->pluck('id');
            $schedule = DB::table('order_of_plays')
                ->whereIn('fixture_id', $fixtureIds)
                ->orderBy('time')->orderBy('venue_id')->orderBy('court')
                ->get(['fixture_id', 'draw_id', 'venue_id', 'court', 'time', 'duration_minutes', 'gap_minutes', 'round_number'])
                ->map(fn ($row) => (array) $row)->values()->all();

            $draft = $record->draft;
            $draft['slots'] = collect($draft['slots'] ?? [])->reject(fn ($source) =>
                ($source['type'] ?? null) === 'player'
                && in_array((int) ($source['id'] ?? 0), $withdrawn, true)
            )->all();

            DB::table('order_of_plays')->whereIn('fixture_id', $fixtureIds)->delete();
            if (Schema::hasTable('schedules')) {
                DB::table('schedules')->where(function ($query) use ($draw, $fixtureIds) {
                    $query->where('draw_id', $draw->id)
                        ->orWhereIn('fixture_id', $fixtureIds);
                })->delete();
            }
            $fixtures->delete();
            $draw->registrations()->detach($withdrawn);
            $draw->update(['published' => false, 'oop_published' => false, 'oop_created' => false]);
            $record->fill([
                'draft' => $draft,
                'graph' => null,
                'fixture_map' => null,
                'revision' => $revision + 1,
            ])->save();

            DrawAuditLog::record($draw->id, 'monrad_withdrawal_redraw_prepared', null, [
                'withdrawn' => $withdrawn,
                'published_before' => $publishedBefore,
                'schedule_published_before' => $schedulePublishedBefore,
                'draft_before' => $draftBefore,
                'schedule_snapshot' => $schedule,
                'schedule_count' => count($schedule),
            ]);

            return ['record' => $record, 'schedule_count' => count($schedule)];
        });
    }

    public function score(Draw $draw, int $fixtureId, ?array $sets, int $revision, bool $resetDependents): FlexibleMonradDraw
    {
        return DB::transaction(function () use ($draw, $fixtureId, $sets, $revision, $resetDependents) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 409, 'The draw is locked.');
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->firstOrFail();
            $this->revision($record, $revision);
            $key = array_search($fixtureId, $record->fixture_map ?? [], true);
            abort_if($key === false, 404);
            $reconciled = $this->resolve($record);
            $fixtures = $draw->drawFixtures()->with('fixtureResults')->get()->keyBy('id');
            $fixture = $fixtures->get($fixtureId);
            abort_unless($fixture, 404);
            if ($sets !== null) {
                abort_unless($fixture->registration1_id && $fixture->registration2_id, 422,
                    'Both active players must qualify before entering a score. Withdrawals advance by walkover.');
            }
            if ($sets !== null) {
                $settings = $draw->settings;
                app(FlexibleMonradScoreValidator::class)->validate(
                    $sets,
                    (int) ($settings?->num_sets ?: 1),
                    $settings?->requiresFullSets() ?? true,
                );
            }
            $old = $fixture->fixtureResults->sortBy('set_nr')->map(fn ($r) => [(int) $r->registration1_score, (int) $r->registration2_score])->values()->all();
            if ($old === ($sets ?? [])) {
                if ($reconciled) $record->increment('revision');
                return $record;
            }
            $newWinner = $sets === null ? null : (count(array_filter($sets, fn ($s) => $s[0] > $s[1])) > count($sets) / 2
                ? $fixture->registration1_id : $fixture->registration2_id);
            $changedWinner = $fixture->winner_registration && (int) $newWinner !== (int) $fixture->winner_registration;
            $descendants = $this->descendants($record->graph['nodes'], $key);
            $affected = array_values(array_filter($descendants, fn ($k) => $fixtures[$record->fixture_map[$k]]->fixtureResults->isNotEmpty()));
            $resetResults = [];
            if ($changedWinner && $affected && ! $resetDependents) {
                abort(409, 'This correction changes later scored matches: '.implode(', ', array_map(fn ($k) => 'Match '.$fixtures[$record->fixture_map[$k]]->match_nr, $affected)).'. Confirm resetting these results before continuing.');
            }
            if ($changedWinner) {
                foreach ($descendants as $child) {
                    $next = $fixtures[$record->fixture_map[$child]];
                    if ($next->fixtureResults->isNotEmpty()) {
                        $resetResults[] = ['fixture_id' => $next->id, 'winner' => $next->winner_registration,
                            'players' => [$next->registration1_id, $next->registration2_id],
                            'sets' => $next->fixtureResults->sortBy('set_nr')->map(fn ($r) => [$r->registration1_score, $r->registration2_score])->values()->all()];
                    }
                    $next->fixtureResults()->delete();
                    $next->update(['winner_registration' => null, 'match_status' => 0]);
                }
            }
            $fixture->fixtureResults()->delete();
            foreach ($sets ?? [] as $i => [$a, $b]) {
                $fixture->fixtureResults()->create(['set_nr' => $i + 1,
                    'registration1_score' => $a, 'registration2_score' => $b,
                    'winner_registration' => $a > $b ? $fixture->registration1_id : $fixture->registration2_id,
                    'loser_registration' => $a > $b ? $fixture->registration2_id : $fixture->registration1_id]);
            }
            $fixture->update(['winner_registration' => $newWinner, 'match_status' => $newWinner ? 1 : 0]);
            $this->resolve($record);
            $record->increment('revision');
            DrawAuditLog::record($draw->id, 'monrad_score_changed', $fixtureId, ['before' => $old, 'after' => $sets,
                'reset_matches' => $changedWinner ? $affected : [], 'reset_results' => $resetResults, 'revision' => $record->revision]);
            return $record->refresh();
        });
    }

    public function state(Draw $draw): array
    {
        $record = FlexibleMonradDraw::where('draw_id', $draw->id)->first();
        $eligible = $this->eligible($draw)->get();
        $assigned = $record?->graph['players'] ?? array_column(array_filter($record?->draft['slots'] ?? [], fn ($s) => $s['type'] === 'player'), 'id');
        $withdrawn = $record?->graph ? $this->withdrawn($draw, $record->graph) : array_values(array_diff(
            $assigned, $this->activeEntries($draw)->whereIn('registrations.id', $assigned)->pluck('registrations.id')->all()
        ));
        $lateWithdrawals = array_values(array_unique(array_merge(
            array_map('intval', array_keys($record?->graph['late_withdrawals'] ?? [])),
            $draw->published ? $withdrawn : []
        )));
        $players = $eligible->merge(Registration::with('players')->whereIn('id', $assigned)->get())
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->displayName(),
                'profiles' => $r->players->map(fn ($player) => [
                    'name' => trim($player->full_name),
                    'url' => URL::signedRoute('public.player.profile', $player),
                ])->filter(fn ($profile) => $profile['name'] !== '')->values()->all(),
                'eligible' => $eligible->contains('id', $r->id) && ! in_array($r->id, $withdrawn, true),
                'withdrawn' => in_array($r->id, $withdrawn, true),
                'late_withdrawal' => in_array($r->id, $lateWithdrawals, true)])->values();
        $fixtures = $draw->drawFixtures()->where('stage', 'FM')->with(['fixtureResults', 'oop.venue'])->get()->keyBy('id');
        $reconciledWithdrawals = array_map('intval', $record?->graph['withdrawn'] ?? []);
        $pendingWithdrawals = array_values(array_diff($withdrawn, $reconciledWithdrawals));
        // Until an organiser chooses redraw or continuation, retain both names
        // and leave the winner unresolved. The published bracket can show the
        // red withdrawal status without making the operational decision itself.
        $progression = $record?->graph ? $this->progression($record, $fixtures, $reconciledWithdrawals) : ['matches' => [], 'positions' => []];
        $matches = [];
        foreach ($record?->graph['nodes'] ?? [] as $key => $node) {
            $fx = $fixtures->get($record->fixture_map[$key]);
            if (! $fx) continue;
            $matches[$key] = $node + $progression['matches'][$key] + ['id' => $fx->id, 'number' => $fx->match_nr,
                'pending_withdrawal_players' => array_map(fn ($id) => in_array((int) $id, $pendingWithdrawals, true) ? (int) $id : null,
                    $progression['matches'][$key]['players']),
                'schedule' => $fx->oop ? ['time' => $fx->oop->time, 'court' => $fx->oop->court, 'venue' => $fx->oop->venue?->name] : null,
                'sets' => $fx->fixtureResults->sortBy('set_nr')->map(fn ($r) => [$r->registration1_score, $r->registration2_score])->values()->all()];
        }
        $draft = $record?->draft ?? ['size' => 32, 'slots' => []];
        // Slot paths are object keys in the editor, including for an empty saved draft.
        $draft['slots'] = (object) $draft['slots'];
        return ['revision' => $record?->revision ?? 0, 'draft' => $draft,
            'best_of' => (int) ($draw->settings?->num_sets ?: 1),
            'generated' => (bool) $record?->graph, 'published' => (bool) $draw->published, 'locked' => (bool) $draw->locked,
            'players' => $players, 'matches' => (object) $matches, 'positions' => $progression['positions'],
            'has_withdrawals' => (bool) $withdrawn,
            'withdrawals_pending' => (bool) array_diff($withdrawn, $record?->graph['withdrawn'] ?? [])];
    }

    public function reconcileWithdrawals(Draw $draw, int $revision): FlexibleMonradDraw
    {
        return DB::transaction(function () use ($draw, $revision) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 409, 'The draw is locked.');
            $record = FlexibleMonradDraw::where('draw_id', $draw->id)->firstOrFail();
            $this->revision($record, $revision);
            abort_unless($record->graph, 422, 'Generate fixtures first.');
            if ($this->resolve($record)) $record->increment('revision');
            return $record->refresh();
        });
    }

    private function withdrawn(Draw $draw, array $graph): array
    {
        $active = $this->activeEntries($draw)->whereIn('registrations.id', $graph['players'])->pluck('registrations.id')->all();
        // A reconciled withdrawal cannot silently re-enter an already played graph.
        return array_values(array_unique(array_merge($graph['withdrawn'] ?? [], array_diff($graph['players'], $active))));
    }

    private function progression(FlexibleMonradDraw $record, $fixtures, array $withdrawn): array
    {
        $input = [];
        foreach ($record->fixture_map as $key => $id) {
            $fixture = $fixtures->get($id);
            abort_unless($fixture, 409, 'A generated fixture is missing. Restore the draw before continuing.');
            $input[$key] = ['players' => [$fixture->registration1_id ? (int) $fixture->registration1_id : null,
                $fixture->registration2_id ? (int) $fixture->registration2_id : null],
                'winner' => $fixture->winner_registration ? (int) $fixture->winner_registration : null,
                'played' => $fixture->fixtureResults->isNotEmpty()];
        }
        return app(FlexibleMonradProgression::class)->resolve($record->graph, $input, $withdrawn);
    }

    private function resolve(FlexibleMonradDraw $record): bool
    {
        $draw = Draw::findOrFail($record->draw_id);
        $fixtures = $draw->drawFixtures()->with('fixtureResults')->get()->keyBy('id');
        $withdrawn = $this->withdrawn($draw, $record->graph);
        $progression = $this->progression($record, $fixtures, $withdrawn);
        $automatic = [];
        $changed = false;
        foreach ($progression['matches'] as $key => $match) {
            $fixture = $fixtures[$record->fixture_map[$key]];
            if ($match['automatic']) $automatic[$key] = $match['automatic'];
            if ($fixture->fixtureResults->isNotEmpty()) continue;
            $fixture->fill(['registration1_id' => $match['players'][0], 'registration2_id' => $match['players'][1],
                'winner_registration' => $match['winner'], 'match_status' => $match['resolved'] ? 3 : 0]);
            $changed = $fixture->isDirty() || $changed;
            $fixture->save();
        }
        $graph = $record->graph;
        $previousAutomatic = $graph['automatic'] ?? [];
        ksort($previousAutomatic);
        ksort($automatic);
        if (($graph['withdrawn'] ?? []) !== $withdrawn || $previousAutomatic !== $automatic) {
            DrawAuditLog::record($draw->id, 'monrad_withdrawals_resolved', null,
                ['withdrawn' => $withdrawn, 'automatic' => $automatic, 'previous_automatic' => $graph['automatic'] ?? []]);
            $graph['withdrawn'] = $withdrawn;
            $graph['automatic'] = $automatic;
            $record->update(['graph' => $graph]);
            $changed = true;
        }
        if ($withdrawn) $draw->registrations()->detach($withdrawn);
        $closedIds = array_map(fn ($key) => $record->fixture_map[$key], array_keys($automatic));
        if ($closedIds) {
            $flags = Fixture::where('draw_id', $draw->id)->whereIn('id', $closedIds)
                ->where(fn ($q) => $q->whereNull('scheduled')->orWhere('scheduled', '!=', 0))->update(['scheduled' => 0]);
            $orders = DB::table('order_of_plays')->whereIn('fixture_id', $closedIds)->delete();
            $schedules = DB::table('schedules')->where('draw_id', $draw->id)->whereIn('fixture_id', $closedIds)->delete();
            $changed = $changed || $flags > 0 || $orders > 0 || $schedules > 0;
        }
        return $changed;
    }

    private function descendants(array $nodes, string $key): array
    {
        $found = [];
        $queue = [$key];
        while ($queue) {
            $source = array_shift($queue);
            foreach ($nodes as $id => $node) {
                if (isset($found[$id])) continue;
                foreach ($node['sources'] as $slot) {
                    if (($slot['match'] ?? null) === $source) {
                        $found[$id] = true;
                        $queue[] = $id;
                    }
                }
            }
        }
        return array_keys($found);
    }

    private function validateDraft(Draw $draw, array $draft): array
    {
        $draft = Validator::make($draft, ['size' => 'required|integer|in:4,8,16,32,64', 'slots' => 'present|array|max:64',
            'slots.*.type' => 'required|in:player,bye', 'slots.*.id' => 'nullable|integer|min:1'])->validate();
        $slots = [];
        $ids = [];
        foreach ($draft['slots'] as $path => $source) {
            if (! preg_match('/^[ab]{1,6}$/', $path) || strlen($path) > log($draft['size'], 2)) $this->fail('Invalid starting position.');
            if (in_array($draw->settings?->workflow, ['playoffs', 'monrad'], true) && strlen($path) !== (int) log($draft['size'], 2)) {
                $this->fail('Place players and byes in the opening round. Choose Custom Monrad for later-round entry.');
            }
            for ($i = 1; $i < strlen($path); $i++) {
                if (isset($draft['slots'][substr($path, 0, $i)])) $this->fail('This position conflicts with an earlier qualifying path.');
            }
            $slots[$path] = ['type' => $source['type']];
            if ($source['type'] === 'player') {
                $id = (int) ($source['id'] ?? 0);
                if (! $id || in_array($id, $ids, true)) $this->fail('Place each player once.');
                $ids[] = $id;
                $slots[$path]['id'] = $id;
            }
        }
        if ($this->eligible($draw)->whereIn('registrations.id', $ids)->count() !== count($ids)) {
            $this->fail('All players must be registered in this draw’s event and category.');
        }
        // The saved workflow, never a submitted mode, controls progression.
        return ['size' => (int) $draft['size'], 'slots' => $slots]
            + ($draw->settings?->workflow ? ['mode' => $draw->settings->workflow] : []);
    }

    private function editable(Draw $draw): void
    {
        abort_if(in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true), 409, 'Use the round robin workspace for this draw.');
        abort_if($draw->locked || $draw->published, 409, 'The draw must be unlocked and unpublished to change its structure.');
        abort_unless($draw->category_event_id, 422, 'Flexible Monrad requires a player category.');
    }

    private function revision(?FlexibleMonradDraw $record, int $revision): void
    {
        abort_if(($record?->revision ?? 0) !== $revision, 409, 'This draw changed in another session. Reload before saving.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['draft' => $message]);
    }
}
