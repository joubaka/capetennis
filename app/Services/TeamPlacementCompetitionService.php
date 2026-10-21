<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\{CategoryEvent, Draw, DrawAuditLog, DrawType, Event, Team, TeamTie};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One age-group league followed by an independently snapshotted placement draw. */
final class TeamPlacementCompetitionService
{
    public function groups(Event $event): array
    {
        $resolver = app(TeamDrawSideResolver::class);
        $groups = [];
        foreach (CategoryEvent::with('category')->where('event_id', $event->id)->get() as $category) {
            $key = $resolver->categoryKey($category->category->name);
            if (in_array($key['gender'], ['boys', 'girls'], true)) {
                $groups[$key['group']]['category_ids'][] = $category->id;
            }
        }
        foreach ($groups as $label => &$group) {
            $group['label'] = strtoupper($label);
            $teams = Team::with('category.category')->whereIn('category_event_id', $group['category_ids'])->get();
            $sizes = $teams->pluck('num_team_members')->map(fn ($n) => (int) $n)->unique();
            $group['size'] = $sizes->count() === 1 ? $sizes->first() : null;
            $group['teams'] = $teams->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'size' => (int) $team->num_team_members])->all();
            $group['round_robin'] = $group['size'] ? $this->defaults($group['size'], false) : [];
            $group['playoff'] = $group['size'] ? $this->defaults($group['size'], true) : [];
        }
        unset($group);
        return array_values($groups);
    }

    public function defaults(int $size, bool $singles): array
    {
        if ($size < 2 || $size > 12 || $size % 2) return [];
        $rows = [];
        $add = function (string $code, string $name, array $positions, string $gender) use (&$rows) {
            $rows[] = ['sequence' => count($rows) + 1, 'rubber_code' => $code, 'name' => $name,
                'home_positions' => $positions, 'away_positions' => $positions, 'gender_rule' => $gender,
                'player_count_per_team' => count($positions), 'is_required' => true];
        };
        foreach ([1 => 'Boys', 2 => 'Girls'] as $offset => $label) {
            $gender = $offset === 1 ? 'male' : 'female';
            if ($singles) for ($rank = 1; $rank <= $size; $rank++) {
                $add(RubberType::SINGLES, "$label singles $rank", [($rank - 1) * 2 + $offset], $gender);
            }
            for ($rank = 1; $rank < $size; $rank += 2) {
                $add(RubberType::DOUBLES, "$label doubles ".(($rank + 1) / 2), [($rank - 1) * 2 + $offset, $rank * 2 + $offset], $gender);
            }
        }
        for ($rank = 1; $rank <= $size; $rank++) $add(RubberType::MIXED_DOUBLES, "Mixed doubles $rank", [$rank * 2 - 1, $rank * 2], 'mixed');
        return $rows;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['competition' => $message]);
    }

    public function format(array $rows, int $size, string $name): array
    {
        if (!$rows || count($rows) > 100) $this->fail('Define between 1 and 100 matches for each phase.');
        $normal = [];
        foreach ($rows as $row) {
            if (!is_array($row)) $this->fail('Each match must contain its type, name and pairing positions.');
            $code = $row['rubber_code'] ?? '';
            if (!in_array($code, [RubberType::SINGLES, RubberType::DOUBLES, RubberType::MIXED_DOUBLES], true)) $this->fail('Choose singles, doubles or mixed doubles.');
            $count = $code === RubberType::SINGLES ? 1 : 2;
            $gender = $code === RubberType::MIXED_DOUBLES ? 'mixed' : ($row['gender_rule'] ?? null);
            if (!in_array($gender, ['male', 'female', 'mixed'], true) || ($code !== RubberType::MIXED_DOUBLES && $gender === 'mixed')) $this->fail('Choose boys or girls for singles and doubles.');
            foreach (['home_positions', 'away_positions'] as $field) {
                $positions = $row[$field] ?? [];
                if (!is_array($positions) || !array_is_list($positions) || count($positions) !== $count || count(array_unique($positions)) !== $count) $this->fail('Each match needs the correct number of distinct roster positions on both sides.');
                foreach ($positions as $position) {
                    if (filter_var($position, FILTER_VALIDATE_INT) === false || $position < 1 || $position > 2 * $size) $this->fail('A roster position is outside the selected team size.');
                    if ($gender !== 'mixed' && (int) $position % 2 !== ($gender === 'male' ? 1 : 0)) $this->fail('Boys use odd positions and girls use even positions.');
                }
                if ($gender === 'mixed' && (int) $positions[0] % 2 === (int) $positions[1] % 2) $this->fail('Mixed doubles needs one boy and one girl on each side.');
                $row[$field] = array_map('intval', array_values($positions));
            }
            if (!is_string($row['name'] ?? null)) $this->fail('Give each match a name.');
            $label = trim($row['name']);
            if ($label === '' || mb_strlen($label) > 100) $this->fail('Give each match a name of at most 100 characters.');
            $normal[] = ['sequence' => count($normal) + 1, 'rubber_code' => $code, 'name' => $label,
                'gender_rule' => $gender, 'player_count_per_team' => $count, 'is_required' => true,
                'home_positions' => $row['home_positions'], 'away_positions' => $row['away_positions']];
        }
        return ['name' => $name, 'min_roster_size' => 2 * $size, 'max_roster_size' => 2 * $size,
            'allow_player_reuse' => true, 'rubbers' => $normal];
    }

    public function create(Event $event, array $input): Draw
    {
        return DB::transaction(function () use ($event, $input) {
            app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $ids = array_values(array_unique(array_map('intval', $input['category_ids']))); sort($ids);
            $group = collect($this->groups($event))->first(function ($group) use ($ids) { $candidate = $group['category_ids']; sort($candidate); return $candidate === $ids; });
            if (!$group || !$group['size'] || !$group['round_robin']) $this->fail('Select one complete age group with equal, even boys and girls team sizes between 2 and 12.');
            $rr = $this->format($input['round_robin'], $group['size'], $group['label'].' round robin');
            $playoff = $this->format($input['playoff'], $group['size'], $group['label'].' placements');
            $fingerprint = hash('sha256', json_encode([$ids, $rr, $playoff]));
            foreach (Draw::where('event_id', $event->id)->get() as $existing) {
                if (($existing->team_draw_selection['competition']['phase'] ?? null) === 'round_robin' && ($existing->team_draw_selection['competition']['group'] ?? null) === $group['label']) {
                    if (($existing->team_draw_selection['competition']['fingerprint'] ?? null) !== $fingerprint) $this->fail('This age group already has a competition with different formats. Open its existing team ties.');
                    return $existing;
                }
            }
            $type = DrawType::where('type', 'team')->get()->first(fn ($type) => app(TeamDrawSelectionService::class)->code($type) === RubberType::MIXED_DOUBLES);
            if (!$type) $this->fail('Configure the mixed doubles team draw type first.');
            $plan = app(TeamDrawSelectionService::class)->plan($event, ['draw_type_id' => $type->id, 'category_ids' => $ids, 'drawName' => $group['label']], false);
            if ($plan['errors'] || count($plan['selection']['mixed_sides']) * 2 !== count($group['teams'])) $this->fail('Every region needs exactly one boys team and one girls team in this age group, with at least two regions.');
            $draw = new Draw;
            $draw->event_id = $event->id; $draw->category_event_id = $ids[0]; $draw->drawType_id = $type->id;
            // Legacy draws.gender is an integer; mixed source rosters live in the selection snapshot.
            $draw->drawName = $group['label'].' round robin'; $draw->gender = 0;
            $draw->team_draw_selection = $plan['selection'] + ['competition' => ['phase' => 'round_robin', 'fingerprint' => $fingerprint, 'group' => $group['label'], 'size' => $group['size'], 'playoff_format' => $playoff]];
            $draw->team_format_snapshot = $rr;
            $draw->team_scoring_rules = app(TeamEventRulesService::class)->defaults() + ['undecided' => true];
            $draw->save();
            $draw->teams_in_draw()->sync($plan['teams']->pluck('id')->all());
            foreach (app(TeamDrawGenerationService::class)->generate($draw, $plan['teams']) as $tie) app(TeamTieGenerationService::class)->generateForTie($tie);
            DrawAuditLog::record($draw->id, 'team_competition_created', null, ['group' => $group['label'], 'size' => $group['size']]);
            return $draw;
        });
    }

    public function configureScoring(Draw $draw, array $rules): void
    {
        DB::transaction(function () use ($draw, $rules) {
            app(TeamDrawAdaptationService::class)->lockEvent($draw->event_id);
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            if (!isset($draw->team_draw_selection['competition'])) $this->fail('This is not a placement competition.');
            if ($draw->locked || $draw->published || $draw->fixtures()->where(fn ($q) => $q->where('match_status', '!=', 0)->orWhereHas('fixtureResults'))->exists() || $draw->teamTies()->whereNotNull('published_at')->exists() || !empty($draw->team_draw_selection['competition']['playoff_draw_id'])) $this->fail('Scoring can only be configured before publication and play.');
            $draw->team_scoring_rules = $rules;
            $draw->save();
            DrawAuditLog::record($draw->id, 'competition_scoring_confirmed', null, ['rules' => $rules]);
        });
    }

    /** Allocate stable identities before qualification, without pretending that teams are known. */
    public function prepareDraft(Draw $source): Draw
    {
        return DB::transaction(function () use ($source) {
            app(TeamDrawAdaptationService::class)->lockEvent($source->event_id);
            $source = Draw::whereKey($source->id)->lockForUpdate()->firstOrFail();
            $config = $source->team_draw_selection['competition'] ?? [];
            if ($source->locked || ($config['phase'] ?? null) !== 'round_robin') $this->fail('Choose an unlocked competition round robin.');
            if (!empty($config['playoff_draw_id'])) return $this->linkedPlayoff($source, $config['playoff_draw_id']);
            if (!empty($config['draft_playoff_draw_id'])) return $this->linkedPlayoff($source, $config['draft_playoff_draw_id']);
            $format = $config['playoff_format'] ?? null;
            if (empty($format['rubbers'])) $this->fail('Save the playoff match format first.');
            $count = app(TeamDrawSideResolver::class)->teams($source)->count();
            if ($count < 2) $this->fail('At least two selected regional sides are required.');
            $draw = new Draw;
            $draw->event_id = $source->event_id; $draw->drawType_id = $source->drawType_id; $draw->category_event_id = $source->category_event_id;
            $draw->drawName = $config['group'].' placement playoffs'; $draw->gender = 0;
            $selection = $source->team_draw_selection;
            unset($selection['decisions']);
            $selection['mixed_sides'] = [];
            $selection['competition'] = ['phase' => 'playoff', 'group' => $config['group'], 'size' => $config['size'],
                'source_draw_id' => $source->id, 'qualification_pending' => true];
            $draw->team_draw_selection = $selection; $draw->team_format_snapshot = $format;
            $draw->team_scoring_rules = $source->team_scoring_rules; $draw->save();
            $draw->venues()->sync($source->venues()->get()->mapWithKeys(fn ($venue) => [$venue->id => ['num_courts' => $venue->pivot->num_courts]])->all());
            foreach (range(1, $count >= 4 ? 2 : 1) as $number) {
                $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => $number,
                    'home_team_id' => null, 'away_team_id' => null, 'status' => TeamTie::STATUS_DRAFT]);
                app(TeamTieGenerationService::class)->generateForTie($tie);
            }
            $selection = $source->team_draw_selection;
            $selection['competition']['draft_playoff_draw_id'] = $draw->id;
            $source->team_draw_selection = $selection; $source->save();
            DrawAuditLog::record($source->id, 'team_playoff_draft_prepared', null, ['playoff_draw_id' => $draw->id, 'fixture_ids' => $draw->fixtures()->pluck('id')->all()]);
            return $draw;
        });
    }

    private function linkedPlayoff(Draw $source, int $id): Draw
    {
        $draw = Draw::where('event_id', $source->event_id)->whereKey($id)->lockForUpdate()->firstOrFail();
        if (($draw->team_draw_selection['competition']['phase'] ?? null) !== 'playoff'
            || (int) ($draw->team_draw_selection['competition']['source_draw_id'] ?? 0) !== (int) $source->id) $this->fail('The linked playoff does not belong to this round robin.');
        return $draw;
    }

    public function playoffs(Draw $source): Draw
    {
        return DB::transaction(function () use ($source) {
            app(TeamDrawAdaptationService::class)->lockEvent($source->event_id);
            $source = Draw::whereKey($source->id)->lockForUpdate()->firstOrFail();
            $selection = $source->team_draw_selection;
            $config = $selection['competition'] ?? [];
            if ($source->locked) $this->fail('Unlock this competition before qualifying teams.');
            if (($config['phase'] ?? null) !== 'round_robin') $this->fail('Choose a competition round-robin group.');
            if (!empty($config['playoff_draw_id'])) return $this->linkedPlayoff($source, $config['playoff_draw_id']);
            if ($source->team_scoring_rules['undecided'] ?? false) $this->fail('Confirm scoring before qualifying teams.');
            $teams = app(TeamDrawSideResolver::class)->teams($source);
            $ties = $source->teamTies()->with('rubbers.teamResults', 'rubbers.draw')->lockForUpdate()->get();
            $decision = app(TeamCompetitionDecisionService::class)->standingsDecision($source);
            if ($decision && !$decision['valid']) $this->fail('The organiser standings decision needs review after changed results or teams.');
            if (!($decision['valid'] ?? false) && ($ties->count() !== $teams->count() * ($teams->count() - 1) / 2 || $ties->contains(fn ($tie) => !app(TeamStandingsService::class)->tieOutcome($tie)['complete']))) $this->fail('Complete every round-robin tie before creating playoffs.');
            $rows = app(TeamStandingsService::class)->forDraw($source);
            if (count($rows) !== $teams->count() || count(array_unique(array_column($rows, 'rank'))) !== count($rows)) $this->fail('Resolve tied standings using the agreed scoring criteria before creating playoffs.');
            $draw = !empty($config['draft_playoff_draw_id']) ? $this->linkedPlayoff($source, $config['draft_playoff_draw_id']) : $this->prepareDraft($source);
            if (!$draw->isPlacementDraft() || $draw->locked || $draw->published || $draw->oop_published
                || $draw->teamTies()->whereNotNull('published_at')->exists()
                || $draw->fixtures()->where(fn ($q) => $q->where('match_status', '!=', 0)->orWhereHas('fixtureResults'))->exists()) $this->fail('The draft playoff is protected or already played; review it before qualification.');
            if ($draw->team_format_snapshot !== $config['playoff_format']) $this->fail('The saved draft match format changed. Review the draft before qualification.');
            $expectedTies = $teams->count() >= 4 ? 2 : 1;
            if ($draw->teamTies()->count() !== $expectedTies || $draw->fixtures()->count() !== $expectedTies * count($config['playoff_format']['rubbers'])) $this->fail('The draft playoff fixtures are incomplete. Review the draft before qualification.');
            unset($selection['decisions']);
            $selection['competition'] = ['phase' => 'playoff', 'group' => $config['group'], 'size' => $config['size'], 'source_draw_id' => $source->id, 'qualification' => $rows, 'qualification_pending' => false];
            $draw->team_draw_selection = $selection; $draw->team_format_snapshot = $config['playoff_format'];
            $draw->team_scoring_rules = $source->team_scoring_rules; $draw->save();
            $qualifiers = array_slice(array_column(array_values(array_filter($rows, fn ($row) => !($row['qualification_excluded'] ?? false))), 'team_id'), 0, 4);
            if (count($qualifiers) < 2) $this->fail('At least two eligible sides are required.');
            $draw->teams_in_draw()->sync($qualifiers);
            $selection['competition']['qualified_team_ids'] = $qualifiers;
            if (count($qualifiers) === 3) $selection['competition']['automatic_third_team_id'] = $qualifiers[2];
            // Restrict composite resolver to qualified sides only.
            $selection['mixed_sides'] = array_intersect_key($selection['mixed_sides'], array_flip($qualifiers));
            $draw->team_draw_selection = $selection; $draw->save();
            foreach ([0, 2] as $offset) {
                if (!isset($qualifiers[$offset + 1])) continue;
                $tie = $draw->teamTies()->where('tie_nr', $offset / 2 + 1)->lockForUpdate()->firstOrFail();
                $tie->update(['home_team_id' => $qualifiers[$offset], 'away_team_id' => $qualifiers[$offset + 1], 'status' => TeamTie::STATUS_DRAFT]);
                foreach ($tie->rubbers()->orderBy('rubber_sequence')->get() as $rubber) {
                    $definition = collect($draw->team_format_snapshot['rubbers'])->firstWhere('sequence', $rubber->rubber_sequence);
                    if (!$definition) $this->fail('Draft playoff matches differ from the saved format.');
                    app(TeamPlayerAutoAssignService::class)->assignForRubber($rubber, $tie->fresh(), new \App\Models\TeamEventFormatRubber($definition));
                }
            }
            if (count($qualifiers) < 4) {
                foreach ($draw->teamTies()->where('tie_nr', 2)->with('rubbers')->get() as $unused) {
                    $removed = $unused->rubbers->map(fn ($rubber) => ['fixture_id' => $rubber->id] + $rubber->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']))->all();
                    $ids = $unused->rubbers->pluck('id')->all();
                    DB::table('published_schedule_assignments')->where('event_id', $source->event_id)->where('draw_id', $draw->id)->where('fixture_kind', 'team')->whereIn('fixture_id', $ids)->delete();
                    $unused->rubbers()->delete(); $unused->delete();
                    DrawAuditLog::record($draw->id, 'unused_placement_bookings_released', null, ['bookings' => $removed]);
                }
            }
            $sourceSelection = $source->team_draw_selection;
            $sourceSelection['competition']['playoff_draw_id'] = $draw->id;
            unset($sourceSelection['competition']['draft_playoff_draw_id']);
            $source->team_draw_selection = $sourceSelection; $source->save();
            $scheduleOptions = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $source->event_id)->value('options'), true) ?: [];
            $playerRest = max(0, (int) ($scheduleOptions['player_rest'] ?? 60));
            foreach ($draw->fixtures()->whereNotNull('scheduled_at')->get() as $fixture) {
                $error = app(\App\Services\Scheduling\UnifiedTeamScheduleService::class)->manualError($draw->event, $fixture, [
                    'venue_id' => $fixture->venue_id, 'court' => $fixture->court_label, 'scheduled_at' => $fixture->scheduled_at,
                    'duration' => $fixture->duration_min ?: 120, 'court_gap' => $fixture->gap_minutes ?? 0, 'player_rest' => $playerRest]);
                if ($error) $fixture->forceFill(['clash_flag' => true])->save();
            }
            DrawAuditLog::record($source->id, 'team_playoffs_created', null, ['playoff_draw_id' => $draw->id, 'qualification' => $rows]);
            return $draw;
        });
    }
}
