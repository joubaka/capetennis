<?php

namespace App\Services\Draw;

use App\Models\Draw;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/** Read-only progress derived from the current tournament records. */
final class DrawSetupGuide
{
    public function state(Draw $draw): array
    {
        $team = $draw->isTeamDraw();
        $competition = $draw->team_draw_selection['competition'] ?? null;
        $linkedPlayoff = $competition && !empty($competition['playoff_draw_id'])
            ? Draw::where('event_id', $draw->event_id)->find($competition['playoff_draw_id']) : null;
        $sourceDraw = $competition && !empty($competition['source_draw_id'])
            ? Draw::where('event_id', $draw->event_id)->find($competition['source_draw_id']) : null;
        $workspace = $team ? ($draw->team_format_snapshot !== null ? route('backend.team-draw.operations', $draw) : route('draws.manage', $draw)) : route('backend.draw.roundrobin.show', $draw);
        $participants = $team ? $draw->teams_in_draw()->count() : $draw->registrations()->reorder()->count();
        $category = $draw->categoryEvent()->where('event_id', $draw->event_id)->exists();
        $format = $team ? !empty($draw->team_format_snapshot['rubbers']) : (bool) $draw->settings?->workflow;
        $warnings = [];
        if ($team) {
            $fixtures = $draw->fixtures()->get();
            $playable = $fixtures;
            if ($draw->team_format_snapshot !== null && $fixtures->contains(fn ($fixture) => !$fixture->team_tie_id)) $warnings[] = 'Review team matches that are missing their tie.';
            $scheduled = $playable->filter(fn ($fixture) => $fixture->scheduled_at && $fixture->venue_id && trim((string) $fixture->court_label) !== '')->count();
            foreach ($draw->teamTies()->with('draw')->get() as $tie) {
                try { app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie); }
                catch (\InvalidArgumentException $exception) { $warnings[] = 'Review team lineups: '.$exception->getMessage(); break; }
            }
        } elseif ($draw->usesFlexibleMonrad()) {
            try { $state = app(FlexibleMonradService::class)->state($draw); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) throw $exception;
                $warnings[] = 'Generated match records need recovery in the draw workspace.';
                $state = ['matches' => [], 'draft' => $draw->flexibleMonrad?->draft ?? [], 'withdrawals_pending' => false];
            }
            $playable = collect((array) $state['matches'])->reject(fn ($match) => $match['automatic']);
            $fixtures = $draw->drawFixtures()->get();
            $scheduled = $playable->filter(fn ($match) => !empty($match['schedule']['time']) && !empty($match['schedule']['court']) && !empty($match['schedule']['venue']))->count();
            $participants = collect((array) ($state['draft']['slots'] ?? []))->where('type', 'player')->count();
            if ($state['withdrawals_pending']) $warnings[] = 'Review withdrawn players in the draw before continuing.';
            if (count($draw->flexibleMonrad?->fixture_map ?? []) !== $fixtures->count()) $warnings[] = 'Generated match records need review.';
        } else {
            $fixtures = $draw->drawFixtures()->with('orderOfPlay')->get();
            $playable = $fixtures->reject(fn ($fixture) => $fixture->winner_registration && (!$fixture->registration1_id || !$fixture->registration2_id));
            $scheduled = $playable->filter(fn ($fixture) => $fixture->orderOfPlay?->time && $fixture->orderOfPlay?->venue_id && trim((string) $fixture->orderOfPlay?->court) !== '')->count();
        }
        $generated = $fixtures->isNotEmpty() && $playable->isNotEmpty();
        if (!$team && in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true)
            && app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw)) {
            $warnings[] = 'Saved groups differ from the generated matches. Review the saved groups and existing matches in player setup before continuing.';
        }
        $format = $format || $generated || (!$team && ($draw->groups()->exists() || $draw->settings?->draw_format_id));
        $eventWide = !$team && !$draw->category_event_id && in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true);
        $scheduleComplete = $generated && $scheduled === $playable->count();
        $snapshotCurrent = $scheduleComplete && $this->snapshotCurrent($draw);
        $steps = [
            ['label' => 'Competition', 'complete' => true, 'detail' => $team ? 'Team tie' : 'Individual singles', 'url' => route('headOffice.show', $draw->event_id)],
            ['label' => 'Participants', 'complete' => $category || $eventWide || (!$team && ($participants > 0 || $generated)), 'detail' => $category ? ($draw->categoryEvent?->category?->name ?? 'Category selected') : ($eventWide ? 'Players from multiple event categories.' : 'Choose the players or category for this draw.'), 'url' => $team ? route('draws.manage', $draw) : $workspace.'#groups'],
            ['label' => 'Competition format', 'complete' => $format, 'detail' => $team ? 'Use the saved team tie format.' : (\App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$draw->settings?->workflow][0] ?? ($format ? 'Existing draw format. Review it in the workspace.' : 'Choose how this draw will run.')), 'url' => $team ? route('draws.manage', $draw) : route('draw.setup.show', $draw)],
            ['label' => 'Match setup', 'complete' => $generated && !$warnings, 'detail' => $generated ? $playable->count().' playable matches generated. Review pairings and positions.' : ($team ? 'Review the selected teams and create their ties.' : 'Place players and generate the draw. Monrad supports 4, 8, 16, 32 or 64 positions.'), 'url' => $workspace],
            ['label' => 'Programme & schedule', 'complete' => $scheduleComplete, 'detail' => $scheduled.' / '.$playable->count().' playable matches have a time, venue and court.', 'url' => route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])],
            ['label' => 'Review & publish', 'complete' => $generated && $scheduleComplete && $snapshotCurrent && !$warnings && $draw->published && $draw->oop_published, 'detail' => $draw->oop_published && !$snapshotCurrent ? 'Saved match times differ from the published schedule. Review and publish the updates explicitly.' : 'Review the draw and match times, then publish each explicitly.', 'url' => $workspace],
        ];
        if ($competition) {
            $steps[1]['detail'] = $competition['group'].' · combined boys and girls regional sides.';
            $steps[2]['detail'] = ($competition['phase'] ?? null) === 'playoff' ? 'Placement playoffs from confirmed group standings.' : 'One round-robin group followed by 1st/2nd and 3rd/4th playoffs.';
            $steps[3]['detail'] .= ' Group and playoff match formats are saved separately.';
            $steps[4]['detail'] .= ($competition['phase'] ?? null) === 'round_robin' && !$linkedPlayoff ? ' This schedules the group phase. Playoff matches become available after confirmed qualification.' : '';
        }
        if ($draw->team_scoring_rules['undecided'] ?? false) {
            $warnings[] = 'Confirm the team scoring rules before publication. You can set up the programme and schedule first.';
            $steps[5]['complete'] = false;
        }
        $next = collect($steps)->search(fn ($step) => !$step['complete']);
        return compact('steps', 'warnings', 'team', 'participants', 'generated', 'scheduleComplete', 'workspace', 'competition', 'linkedPlayoff', 'sourceDraw') + ['next' => $next === false ? 5 : $next];
    }

    private function snapshotCurrent(Draw $draw): bool
    {
        $team = $draw->isTeamDraw();
        $kind = $team ? 'team' : 'individual';
        $working = $team
            ? $draw->fixtures()->whereNotNull('scheduled_at')->get()->map(fn ($fixture) => ['fixture_kind' => $kind, 'fixture_id' => $fixture->id, 'scheduled_at' => $fixture->scheduled_at, 'venue_id' => $fixture->venue_id, 'court' => $fixture->court_label, 'duration' => $fixture->duration_min])
            : $draw->drawFixtures()->whereHas('orderOfPlay', fn ($query) => $query->whereNotNull('time'))->with('orderOfPlay')->get()->map(fn ($fixture) => ['fixture_kind' => $kind, 'fixture_id' => $fixture->id, 'scheduled_at' => $fixture->orderOfPlay->time, 'venue_id' => $fixture->orderOfPlay->venue_id, 'court' => $fixture->orderOfPlay->court, 'duration' => $fixture->orderOfPlay->duration_minutes]);
        $published = DB::table('published_schedule_assignments')->where('event_id', $draw->event_id)->where('draw_id', $draw->id)->get()->map(fn ($row) => (array) $row);
        $normalize = fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row['fixture_kind'].':'.$row['fixture_id'] => [
            Carbon::parse($row['scheduled_at'])->format('Y-m-d H:i:s'), (string) (int) $row['venue_id'], (string) $row['court'], (string) (int) ($row['duration'] ?: ($row['fixture_kind'] === 'team' ? 120 : 75)),
        ]])->sortKeys()->all();
        return $working->isNotEmpty() && $normalize($working) === $normalize($published);
    }
}
