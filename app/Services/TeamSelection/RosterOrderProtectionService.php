<?php

namespace App\Services\TeamSelection;

use App\Models\{Draw, TeamFixture, TeamFixturePlayer, TeamPlayer, NoProfileTeamPlayer, TeamSelectionInvitation, TeamTie};
use App\Services\TeamDrawAdaptationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Preview the canonical mutation under rollback, then recheck it under the same locks on approval. */
class RosterOrderProtectionService
{
    public static bool $previewing = false;

    public function execute(int $eventId, int $actorId, array $proposal, callable $mutation, bool $preview, ?string $fingerprint): array
    {
        DB::beginTransaction();
        $previousPreviewing = self::$previewing;
        // Keep nontransactional UI flashes and debug logs out of both speculative attempts.
        self::$previewing = true;
        try {
            $adaptation = app(TeamDrawAdaptationService::class);
            app()->instance(TeamDrawAdaptationService::class, $adaptation);
            $adaptation->lastEventReports = [];
            $adaptation->lockEvent($eventId);
            $before = $this->snapshot($eventId);
            $token = hash_hmac('sha256', json_encode([$eventId, $actorId, $proposal, $before], JSON_THROW_ON_ERROR), (string) config('app.key'));
            if (!$preview && (!$fingerprint || !hash_equals($token, $fingerprint))) {
                throw ValidationException::withMessages(['order' => 'Review the move again: the roster or schedule changed, or confirmation is missing.']);
            }
            $mutation();
            $after = $this->snapshot($eventId);
            $this->verifyRequestedOrder($proposal, $before, $after);
            $reports = app(TeamDrawAdaptationService::class)->lastEventReports;
            $warnings = collect($reports)->flatMap(fn ($report) => $report['warnings'] ?? [])->unique()->values()->all();
            $sourceTeamId = $proposal['team_id'] ?? collect($before['invitations'])->firstWhere('id', $proposal['invitation_id'] ?? 0)['team_id'] ?? collect($before['imported'])->firstWhere('id', $proposal['slot_id'] ?? 0)['team_id'] ?? null;
            $sourceTeam = collect($before['teams'])->firstWhere('id', $sourceTeamId);
            $affectedDrawIds = collect($before['draws'])->filter(function ($draw) use ($sourceTeamId, $sourceTeam, $before) {
                if (!$sourceTeamId) return true;
                $selection = json_decode($draw['team_draw_selection'] ?? 'null', true) ?: [];
                // Legacy draws without recorded sources cannot be proven unrelated to this roster.
                if (empty($selection['category_ids']) && !collect($before['ties'])->contains(fn ($tie) => $tie['draw_id'] == $draw['id'])
                    && collect($before['fixtures'])->contains(fn ($fixture) => $fixture['draw_id'] == $draw['id'] && !$fixture['team_tie_id'])) return true;
                return in_array((int) data_get($sourceTeam, 'category_event_id', 0), array_map('intval', $selection['category_ids'] ?? []), true)
                    || collect($before['ties'])->contains(fn ($tie) => $tie['draw_id'] == $draw['id'] && ($tie['home_team_id'] == $sourceTeamId || $tie['away_team_id'] == $sourceTeamId));
            })->pluck('id')->all();
            $blocked = [];
            foreach (['publication', 'results'] as $immutable) {
                if ($before[$immutable] != $after[$immutable]) $blocked[] = 'This move would change published schedule or result history. Nothing has been changed.';
            }
            $protectedIds = TeamFixture::whereIn('id', collect($before['fixtures'])->pluck('id'))->get()
                ->filter(fn ($fixture) => app(\App\Services\Scheduling\UnifiedTeamScheduleService::class)->protected($fixture))->pluck('id');
            if (collect($before['lineups'])->whereIn('team_fixture_id', $protectedIds)->values()->all() !== collect($after['lineups'])->whereIn('team_fixture_id', $protectedIds)->values()->all()) {
                $blocked[] = 'Started match participants must be retained. Nothing has been changed.';
            }
            $bookings = fn ($state) => collect($state['fixtures'])->mapWithKeys(fn ($fixture) => [$fixture['id'] => collect($fixture)->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes', 'scheduled'])->all()])->all();
            if ($bookings($before) !== $bookings($after)) $blocked[] = 'This move would change match bookings. Nothing has been changed.';
            foreach ($reports as $drawId => $report) {
                if ($report['cleared_fixture_ids'] || $report['removed_fixture_ids'] || $report['added_fixture_ids'] || ($report['review_required'] && in_array($drawId, $affectedDrawIds)) || $report['review_tie_ids']) {
                    $blocked = array_merge($blocked, $report['warnings']);
                    $blocked[] = 'This move cannot preserve the existing matches and schedule. Nothing has been changed.';
                }
            }
            // Locked and legacy draws cannot safely resolve a new playing order.
            foreach ($before['draws'] as $draw) {
                if (!in_array($draw['id'], $affectedDrawIds)) continue;
                $drawFixtures = collect($before['fixtures'])->where('draw_id', $draw['id']);
                if ($drawFixtures->contains(fn ($fixture) => !$fixture['team_tie_id']) && !collect($before['ties'])->contains(fn ($tie) => $tie['draw_id'] == $draw['id'])) {
                    $blocked[] = 'Legacy regional fixtures have no recorded source teams. Ask the organiser to review them before changing the playing order.';
                }
                if ($draw['locked'] && $drawFixtures->isNotEmpty()) {
                    $blocked[] = 'A locked draw has existing matches. Ask the organiser to review it before changing the playing order.';
                }
            }
            $changed = [];
            $unrelatedChange = false;
            $sourceOwns = function ($slot, int $side, array $state) use ($sourceTeamId): bool {
                if (!$slot || !$sourceTeamId) return !$sourceTeamId;
                $snapshot = json_decode($slot['participant_snapshot'] ?? 'null', true) ?: [];
                if (isset($snapshot[$side]['source_team_id'])) return $snapshot[$side]['source_team_id'] == $sourceTeamId;
                return collect($state['roster'])->contains(fn ($row) => $row['team_id'] == $sourceTeamId && $row['player_id'] && $row['player_id'] == $slot['team'.$side.'_id'])
                    || collect($state['imported'])->contains(fn ($row) => $row['team_id'] == $sourceTeamId && $row['id'] == $slot['team'.$side.'_no_profile_id']);
            };
            $beforeSlots = collect($before['lineups'])->keyBy('id');
            foreach ($after['lineups'] as $slot) {
                $old = $beforeSlots->get($slot['id']);
                foreach ([1, 2] as $side) {
                    if ($old && ($old['team'.$side.'_id'] !== $slot['team'.$side.'_id'] || $old['team'.$side.'_no_profile_id'] !== $slot['team'.$side.'_no_profile_id'])
                        && !$sourceOwns($old, $side, $before) && !$sourceOwns($slot, $side, $after)) $unrelatedChange = true;
                }
                if (!$old || collect(['team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id'])->contains(fn ($key) => $old[$key] !== $slot[$key])) {
                    if (collect([1, 2])->contains(fn ($side) => $sourceOwns($old, $side, $before) || $sourceOwns($slot, $side, $after))) $changed[] = (int) $slot['team_fixture_id'];
                }
            }
            if ($unrelatedChange) {
                $blocked = ['Another team has a lineup requiring organiser review. This move was not saved and no bookings were changed.'];
                $warnings = [];
            }
            $names = function (array $state, int $fixtureId) use ($sourceOwns): string {
                return collect($state['lineups'])->where('team_fixture_id', $fixtureId)->flatMap(function ($slot) use ($sourceOwns, $state) {
                    $snapshot = json_decode($slot['participant_snapshot'] ?? 'null', true) ?: [];
                    return collect([1, 2])->map(function ($side) use ($slot, $snapshot, $sourceOwns, $state) {
                        if (!$sourceOwns($slot, $side, $state)) return null;
                        if (!empty($snapshot[$side]['name'])) return $snapshot[$side]['name'];
                        if ($slot['team'.$side.'_id']) return \App\Models\Player::find($slot['team'.$side.'_id'])?->full_name;
                        $imported = NoProfileTeamPlayer::find($slot['team'.$side.'_no_profile_id']);
                        return $imported ? trim($imported->name.' '.$imported->surname) : null;
                    })->filter()->all();
                })->unique()->implode(', ');
            };
            $affected = collect($before['fixtures'])->whereIn('id', array_unique($changed))->map(fn ($fixture) => [
                'id' => $fixture['id'], 'draw_id' => $fixture['draw_id'], 'match_nr' => $fixture['match_nr'],
                'scheduled_at' => $fixture['scheduled_at'], 'court' => $fixture['court_label'], 'venue_id' => $fixture['venue_id'],
                'before_players' => $names($before, $fixture['id']), 'after_players' => $names($after, $fixture['id']),
            ])->values()->all();
            $result = ['preview' => true, 'fingerprint' => $token, 'can_confirm' => !$blocked,
                'affected_matches' => $affected, 'warnings' => array_values(array_unique($warnings)),
                'blockers' => array_values(array_unique($blocked)),
                'message' => $blocked ? 'The proposed move cannot be saved safely. Your current order and bookings are unchanged.' : 'Upcoming match lineups will follow the new playing order. Existing times, courts, results, payments and ranking history will be retained.'];
            if ($preview) {
                DB::rollBack();
                return $result;
            }
            if ($blocked) throw ValidationException::withMessages(['order' => implode(' ', array_unique($blocked))]);
            DB::commit();
            return ['preview' => false];
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        } finally {
            self::$previewing = $previousPreviewing;
        }
    }

    private function snapshot(int $eventId): array
    {
        $draws = Draw::where('event_id', $eventId)->orderBy('id')->lockForUpdate()->get();
        $drawIds = $draws->pluck('id');
        $fixtures = TeamFixture::whereIn('draw_id', $drawIds)->orderBy('id')->lockForUpdate()->get();
        $teamIds = DB::table('teams')->whereIn('category_event_id', DB::table('category_events')->where('event_id', $eventId)->select('id'))->pluck('id');
        $imports = DB::table('team_selection_imports')->where('event_id', $eventId)->orderBy('id')->lockForUpdate()->get()->all();
        $bookedDates = $fixtures->pluck('scheduled_at')->filter();
        $options = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $eventId)->value('options'), true) ?: [];
        $padding = max(1440, (int) ($options['player_rest'] ?? 60) + (int) ($fixtures->max('duration_min') ?: 120));
        $window = $bookedDates->isEmpty() ? null : [\Carbon\Carbon::parse($bookedDates->min())->subMinutes($padding), \Carbon\Carbon::parse($bookedDates->max())->addMinutes($padding)];
        $calendar = DB::table('team_fixtures')->whereNotNull('scheduled_at')->when($window, fn ($query) => $query->whereBetween('scheduled_at', $window), fn ($query) => $query->whereRaw('1 = 0'))->orderBy('id')->get();
        $individual = DB::table('order_of_plays')->whereNotNull('time')->when($window, fn ($query) => $query->whereBetween('time', $window), fn ($query) => $query->whereRaw('1 = 0'))->orderBy('id')->get();
        $individualFixtures = DB::table('fixtures')->whereIn('id', $individual->pluck('fixture_id'))->orderBy('id')->get();
        $profileIds = TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $teamIds)->pluck('player_id')->merge(NoProfileTeamPlayer::whereIn('team_id', $teamIds)->pluck('player_profile'))->filter()->unique();
        return [
            'draws' => $draws->map->getAttributes()->all(),
            'teams' => DB::table('teams')->whereIn('id', $teamIds)->orderBy('id')->get()->all(),
            'categories' => DB::table('category_events')->where('event_id', $eventId)->orderBy('id')->get()->all(),
            'imports' => $imports,
            'ties' => TeamTie::whereIn('draw_id', $drawIds)->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
            'fixtures' => $fixtures->map->getAttributes()->all(),
            'lineups' => TeamFixturePlayer::whereIn('team_fixture_id', $fixtures->pluck('id'))->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
            'roster' => TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $teamIds)->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
            'imported' => NoProfileTeamPlayer::whereIn('team_id', $teamIds)->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
            'invitations' => TeamSelectionInvitation::where('event_id', $eventId)->orderBy('id')->lockForUpdate()->get()->map->getAttributes()->all(),
            'schedule_options' => DB::table('event_venue_schedule_drafts')->where('event_id', $eventId)->orderBy('id')->get()->all(),
            'settings' => DB::table('events')->where('id', $eventId)->first(),
            'courts' => DB::table('event_venue_courts')->where('event_id', $eventId)->orderBy('id')->get()->all(),
            'event_venues' => DB::table('event_venues')->where('event_id', $eventId)->orderBy('venue_id')->get()->all(),
            'draw_venues' => DB::table('draw_venues')->whereIn('draw_id', $drawIds)->orderBy('draw_id')->orderBy('venue_id')->get()->all(),
            'allocations' => DB::table('draw_venue_court_allocations')->whereIn('draw_id', $drawIds)->orderBy('id')->get()->all(),
            'publication' => DB::table('published_schedule_assignments')->where('event_id', $eventId)->orderBy('id')->get()->all(),
            'results' => DB::table('team_fixture_results')->whereIn('team_fixture_id', $fixtures->pluck('id'))->orderBy('id')->get()->all(),
            // A booking in another event may introduce a player clash between review and approval.
            'calendar' => $calendar->all(),
            'calendar_lineups' => DB::table('team_fixture_players')->whereIn('team_fixture_id', $calendar->pluck('id'))->orderBy('id')->get()->all(),
            'individual_calendar' => $individual->all(),
            'individual_fixtures' => $individualFixtures->all(),
            'registrations' => DB::table('player_registrations')->whereIn('registration_id', $individualFixtures->pluck('registration1_id')->merge($individualFixtures->pluck('registration2_id'))->filter())->orWhereIn('player_id', $profileIds)->orderBy('registration_id')->orderBy('player_id')->get()->all(),
            'formats' => DB::table('team_event_formats')->whereIn('id', $draws->pluck('team_event_format_id')->filter())->orderBy('id')->get()->all(),
            'format_rubbers' => DB::table('team_event_format_rubbers')->whereIn('format_id', $draws->pluck('team_event_format_id')->filter())->orderBy('id')->get()->all(),
        ];
    }

    private function verifyRequestedOrder(array $proposal, array $before, array $state): void
    {
        if (isset($proposal['invitation_ids'])) {
            $actual = collect($state['invitations'])->whereIn('id', $proposal['invitation_ids'])->sortBy('roster_rank')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $expected = array_map('intval', $proposal['invitation_ids']);
        } elseif (isset($proposal['slot_ids'])) {
            $actual = collect($state['imported'])->whereIn('id', $proposal['slot_ids'])->sortBy('rank')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $expected = array_map('intval', $proposal['slot_ids']);
        } elseif (isset($proposal['invitation_id']) || isset($proposal['slot_id'])) {
            $field = isset($proposal['invitation_id']) ? 'invitations' : 'imported';
            $rank = $field === 'invitations' ? 'roster_rank' : 'rank';
            $id = $proposal['invitation_id'] ?? $proposal['slot_id'];
            $original = collect($before[$field])->firstWhere('id', $id);
            $current = collect($state[$field])->firstWhere('id', $id);
            if (!$original || !$current || ($proposal['direction'] === 'up' ? $current[$rank] >= $original[$rank] : $current[$rank] <= $original[$rank])) {
                throw ValidationException::withMessages(['order' => 'The requested move could not be verified. Nothing has been changed.']);
            }
            return;
        } else return;
        if ($actual !== $expected) throw ValidationException::withMessages(['order' => 'The new playing order could not be verified. Nothing has been changed.']);
    }
}
