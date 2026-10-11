<?php

namespace App\Services;

use App\Models\{Draw, Event, Team, User};
use Illuminate\Support\Facades\DB;

final class AgeGroupConvenerStandingsService
{
    public function age(Draw $draw): ?string
    {
        $category = (int) $draw->categoryEvent?->event_id === (int) $draw->event_id ? $draw->categoryEvent->category?->name : null;
        $category ??= (int) $draw->team_category?->eventId === (int) $draw->event_id ? $draw->team_category->ageGroup : null;
        return preg_match('/\b(?:u\s*[\/-]?\s*|under\s*-?\s*)(\d+)\b/i', $category.' '.$draw->drawName.' '.$draw->gender, $matches)
            ? 'U'.(int) $matches[1] : null;
    }

    public function draws(Event $event)
    {
        return $event->draws()->with(['categoryEvent.category', 'team_category', 'draw_types'])->get()
            ->each(fn (Draw $draw) => $draw->setRelation('event', $event));
    }

    public function ages(Event $event): array
    {
        return $this->draws($event)->map(fn ($draw) => $this->age($draw))->filter()->unique()->sort(SORT_NATURAL)->values()->all();
    }

    public function assignedAges(Event $event, User $actor): array
    {
        return DB::table('event_age_group_conveners')->where('event_id', $event->id)->where('user_id', $actor->id)
            ->orderBy('age_group')->pluck('age_group')->all();
    }

    public function complete(Event $event, string $age): bool
    {
        $draws = $this->draws($event)->filter(fn ($draw) => $this->age($draw) === $age);
        if ($draws->isEmpty()) { return false; }
        $teamIds = Team::query()->whereHas('category', fn ($query) => $query->where('event_id', $event->id))->pluck('id')->all();
        foreach ($draws as $draw) {
            $fixtures = $draw->fixtures()->with('teamResults')->get();
            $individuals = $draw->ind_fixtures()->with('fixtureResults')->get();
            if ($fixtures->isEmpty() && $individuals->isEmpty()) { return false; }
            foreach ($individuals as $fixture) {
                $fixture->setRelation('draw', $draw);
                $winner = app(IndividualMatchOutcomeService::class)->winner($fixture);
                // A processed bye has an explicit zero side and its surviving player recorded as winner.
                if (!$winner && ((int) $fixture->registration1_id === 0 || (int) $fixture->registration2_id === 0)
                    && ($fixture->registration1_id !== null && $fixture->registration2_id !== null)) {
                    $winner = (int) $fixture->winner_registration;
                }
                if (!$winner || !in_array((int) $winner, [(int) $fixture->registration1_id, (int) $fixture->registration2_id], true)) { return false; }
            }
            foreach ($fixtures as $fixture) {
                $fixture->setRelation('draw', $draw);
                if (!app(TeamRubberResultService::class)->outcome($fixture)['complete']) { return false; }
            }
            foreach ($draw->teamTies()->with(['rubbers.teamResults', 'rubbers.draw'])->get() as $tie) {
                if (!in_array($tie->home_team_id, $teamIds, true) || !in_array($tie->away_team_id, $teamIds, true)
                    || $tie->rubbers->contains(fn ($rubber) => (int) $rubber->draw_id !== (int) $draw->id)) { return false; }
                if (!app(TeamStandingsService::class)->tieOutcome($tie)['complete']) { return false; }
            }
        }
        return true;
    }

    public function standings(Event $event, User $actor, string $age): ?array
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql') {
            if ($connection->transactionLevel() !== 0 && !app()->environment('testing')) {
                throw new \RuntimeException('Convener standings require an independent consistent read.');
            }
            if ($connection->transactionLevel() === 0) {
                $sources = ['event_age_group_conveners', 'events', 'eventtypes', 'draws', 'draw_types', 'category_events', 'categories',
                    'team_categories', 'teams', 'team_ties', 'team_fixtures', 'team_fixture_results', 'team_event_rules',
                    'team_event_formats', 'team_event_format_rubbers', 'event_regions', 'team_regions', 'draw_settings', 'fixtures', 'fixture_results'];
                $tables = $connection->table('information_schema.tables')->where('table_schema', $connection->getDatabaseName())
                    ->whereIn('table_name', array_map(fn ($table) => $connection->getTablePrefix().$table, $sources))
                    ->selectRaw('TABLE_NAME as table_name, ENGINE as engine')->get();
                if ($tables->count() !== count($sources) || $tables->contains(fn ($table) => strcasecmp($table->engine ?? '', 'InnoDB') !== 0)) {
                    throw new \RuntimeException('Convener standings require transactional source tables.');
                }
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $connection->statement('SET TRANSACTION READ ONLY');
            }
        }
        return $connection->transaction(function () use ($event, $actor, $age) {
            abort_unless($event->isTeam() && in_array($age, $this->assignedAges($event, $actor), true), 403);
            if (!$this->complete($event, $age)) { return null; }
            $data = app(EventStandingsService::class)->forEvent($event, ['age' => $age]);
            // Return only the assigned age's ranked rows, never event totals or filter options.
            return ['rows' => $data['ageStandings'][$age] ?? [], 'mixedRules' => $data['ageMixedRules'][$age] ?? false];
        });
    }
}
