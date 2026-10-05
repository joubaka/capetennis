<?php

namespace App\Services;

use App\Domain\TeamDraw\RubberType;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamDrawSelectionService
{
    public function __construct(private TeamDrawSideResolver $sides, private TeamDrawReadinessService $readiness,
        private TeamDrawGenerationService $generator, private TeamTieGenerationService $ties) {}

    public function code(DrawType $type): ?string
    {
        $name = mb_strtolower($type->drawTypeName);
        if (str_contains($name, 'mixed')) return RubberType::MIXED_DOUBLES;
        if (str_contains($name, 'reverse')) return RubberType::REVERSE_SINGLES;
        if (str_contains($name, 'double')) return RubberType::DOUBLES;
        if (str_contains($name, 'single')) return RubberType::SINGLES;
        return null;
    }

    public function plan(Event $event, array $item): array
    {
        $type = DrawType::where('type', 'team')->findOrFail($item['draw_type_id']);
        $code = $this->code($type);
        if (!$code) throw new \InvalidArgumentException('Choose singles, reverse singles, doubles or mixed doubles.');
        $ids = array_values(array_unique(array_map('intval', $item['category_ids']))); sort($ids);
        $categories = CategoryEvent::with('category')->where('event_id', $event->id)->whereIn('id', $ids)->get();
        if ($categories->count() !== count($ids)) throw new \InvalidArgumentException('Every category must belong to this event.');
        $teams = Team::with(['category.category', 'team_players.player', 'team_players_no_profile'])->whereIn('category_event_id', $ids)->orderBy('id')->get();
        $teams = $teams->map(fn ($team) => $this->sides->activeRoster($team));
        $selection = ['category_ids' => $ids, 'rubber_code' => $code, 'mixed_sides' => []];
        $errors = [];
        if ($code === RubberType::MIXED_DOUBLES) {
            $keys = $categories->map(fn ($c) => $this->sides->categoryKey($c->category->name));
            if ($keys->pluck('group')->unique()->count() !== 1 || $keys->contains(fn ($k) => !in_array($k['gender'], ['boys', 'girls'], true))
                || !$keys->pluck('gender')->contains('boys') || !$keys->pluck('gender')->contains('girls')) {
                throw new \InvalidArgumentException('Mixed doubles needs boys and girls categories for the same age and division.');
            }
            $complete = collect();
            foreach ($teams->groupBy('region_id') as $region => $sources) {
                $boys = $sources->filter(fn ($t) => $this->sides->categoryKey($t->category->category->name)['gender'] === 'boys');
                $girls = $sources->filter(fn ($t) => $this->sides->categoryKey($t->category->category->name)['gender'] === 'girls');
                if (!$region || $boys->count() > 1 || $girls->count() > 1) {
                    $errors[] = $sources->pluck('name')->implode(', ').': connect exactly one boys and one girls team in the same region; missing or ambiguous partner.';
                    continue;
                }
                // A region without both source teams does not field a mixed side.
                // Schedule the available sides normally, including round-robin byes.
                if ($boys->isEmpty() || $girls->isEmpty()) continue;
                $side = $this->sides->combine($boys->first(), $girls->first());
                $selection['mixed_sides'][$side->id] = ['boys' => $boys->first()->id, 'girls' => $girls->first()->id, 'region_id' => (int) $region, 'name' => $side->name];
                $complete->push($side);
            }
            $teams = $complete;
        }
        $format = !empty($item['format_id']) ? TeamEventFormat::forEvent($event->id)->with('rubbers')->findOrFail($item['format_id'])
            : TeamEventFormat::where('event_id', $event->id)->where('is_default', true)->with('rubbers')->first();
        if ($format) {
            $snapshot = $format->toArray();
            $snapshot['rubbers'] = $format->rubbers->where('rubber_code', $code)->values()->map->toArray()->all();
            if (!$snapshot['rubbers']) throw new \InvalidArgumentException('The selected format has no '.str_replace('_', ' ', $code).' rubbers. Choose another format or use roster positions.');
        } else {
            $maxRank = max(1, (int) $teams->flatMap(fn ($t) => $t->team_players->concat($t->team_players_no_profile))->max('rank'));
            $rubbers = [];
            for ($rank = 1; $rank <= $maxRank; $rank += in_array($code, [RubberType::DOUBLES, RubberType::MIXED_DOUBLES], true) ? 2 : 1) {
                $home = in_array($code, [RubberType::DOUBLES, RubberType::MIXED_DOUBLES], true) ? [$rank, $rank + 1] : [$rank];
                $away = $code === RubberType::REVERSE_SINGLES ? [$rank % 2 ? $rank + 1 : $rank - 1] : $home;
                $rubbers[] = ['sequence' => count($rubbers) + 1, 'rubber_code' => $code, 'name' => ucfirst(str_replace('_', ' ', $code)).' '.(count($rubbers) + 1),
                    'player_count_per_team' => count($home), 'home_positions' => $home, 'away_positions' => $away,
                    'gender_rule' => $code === RubberType::MIXED_DOUBLES ? 'mixed' : null, 'is_required' => true];
            }
            $snapshot = ['name' => 'Roster positions · '.$type->name, 'min_roster_size' => 1, 'max_roster_size' => 24, 'allow_player_reuse' => false, 'rubbers' => $rubbers];
        }
        $effective = new TeamEventFormat($snapshot);
        $effective->setRelation('rubbers', collect($snapshot['rubbers'])->map(fn ($r) => new TeamEventFormatRubber($r)));
        $preview = $this->readiness->preview($teams, $effective);
        $preview['warnings'] = array_values(array_unique(array_merge($preview['warnings'], $errors)));
        $preview['ready'] = !$preview['warnings'];
        $preview['can_create'] = !$errors && $teams->count() >= 2;
        if ($teams->count() < 2) $errors[] = 'At least two complete teams are needed to create this draw.';
        return compact('type', 'teams', 'selection', 'snapshot', 'format', 'preview', 'errors') + ['name' => $item['drawName']];
    }

    public function create(Event $event, array $items, string $batchKey): array
    {
        $fingerprint = hash('sha256', json_encode($items));
        return DB::transaction(function () use ($event, $items, $batchKey, $fingerprint) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $existing = Draw::where('event_id', $event->id)->where('team_draw_batch_key', $batchKey)->orderBy('team_draw_batch_index')->get();
            if ($existing->isNotEmpty()) {
                if ($existing->count() !== count($items) || $existing->contains(fn ($d) => ($d->team_draw_selection['request_fingerprint'] ?? null) !== $fingerprint)) {
                    throw ValidationException::withMessages(['batch_key' => 'This creation request has already been used for a different selection. Preview again.']);
                }
                return $existing->map(fn ($d) => ['id' => $d->id, 'name' => $d->drawName])->all();
            }
            $plans = [];
            foreach ($items as $index => $item) {
                try { $plan = $this->plan($event, $item); }
                catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(["draws.$index" => $e->getMessage()]); }
                if ($plan['errors']) throw ValidationException::withMessages(["draws.$index" => $plan['errors']]);
                $plans[] = $plan;
            }
            $created = [];
            foreach ($plans as $index => $plan) {
                $draw = new Draw;
                $draw->event_id = $event->id; $draw->drawName = $plan['name']; $draw->drawType_id = $plan['type']->id;
                $draw->category_event_id = $plan['selection']['category_ids'][0];
                $draw->team_draw_selection = $plan['selection'] + ['request_fingerprint' => $fingerprint];
                $draw->team_draw_batch_key = $batchKey; $draw->team_draw_batch_index = $index;
                $draw->team_event_format_id = $plan['format']?->id;
                $draw->team_format_snapshot = $plan['snapshot'];
                $draw->team_scoring_rules = app(TeamEventRulesService::class)->forEvent($event);
                $draw->save();
                $draw->teams_in_draw()->sync($plan['teams']->pluck('id')->all());
                $ties = $this->generator->generate($draw, $plan['teams']);
                foreach ($ties as $tie) $this->ties->generateForTie($tie);
                $created[] = ['id' => $draw->id, 'name' => $draw->drawName, 'ties_count' => $ties->count()];
            }
            return $created;
        });
    }
}
