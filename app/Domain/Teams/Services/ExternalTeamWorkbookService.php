<?php

namespace App\Domain\Teams\Services;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ExternalTeamWorkbookService
{
    public function __construct(private ExternalTeamRosterService $rosters) {}

    public function assertRegionBelongsToEvent(Event $event, TeamRegion $region): void
    {
        abort_unless($event->regions()->whereKey($region->id)->exists(), 404);
    }

    public function preview(
        Event $event,
        TeamRegion $region,
        array $parsedTeams,
        string $teamPrefix,
        int $expectedPlayers
    ): array {
        $this->assertRegionBelongsToEvent($event, $region);

        return array_map(function (array $parsed) use ($event, $region, $teamPrefix, $expectedPlayers): array {
            $errors = $parsed['errors'];
            $category = $this->findCategory($parsed['category']);
            $categoryEvent = $category
                ? CategoryEvent::query()->where('event_id', $event->id)->where('category_id', $category->id)->first()
                : null;
            $team = $categoryEvent
                ? Team::query()->where('region_id', $region->id)->where('category_event_id', $categoryEvent->id)->first()
                : null;

            if ($team && (int) $team->num_team_members !== $expectedPlayers) {
                $errors[] = "Existing team {$team->name} has {$team->num_team_members} slots; this import expects {$expectedPlayers}.";
            }
            if ($team) {
                $errors = array_merge($errors, $this->rosters->validateImport($team, $parsed['players']));
            }

            $errors = array_values(array_unique($errors));

            return array_merge($parsed, [
                'team_name' => $team?->name ?? trim($teamPrefix.' '.$parsed['category']),
                'existing_team_id' => $team?->id,
                'action' => $team ? 'Update existing team' : 'Create no-profile team',
                'errors' => $errors,
                'selectable' => $errors === [],
            ]);
        }, $parsedTeams);
    }

    public function import(
        Event $event,
        TeamRegion $region,
        array $previewTeams,
        array $selectedKeys,
        string $teamPrefix,
        int $expectedPlayers,
        User $actor
    ): array {
        $selectedKeys = array_values(array_unique(array_map('strval', $selectedKeys)));
        $available = collect($previewTeams)->keyBy('key');
        $unknown = array_values(array_diff($selectedKeys, $available->keys()->all()));

        if ($selectedKeys === []) {
            throw ValidationException::withMessages(['teams' => 'Select at least one complete team to import.']);
        }
        if ($unknown !== []) {
            throw ValidationException::withMessages(['teams' => 'The selected team list does not match the workbook preview.']);
        }

        foreach ($selectedKeys as $key) {
            $team = $available->get($key);
            if (! $team['selectable']) {
                throw ValidationException::withMessages([
                    'teams' => "{$team['category']} cannot be imported until its roster errors are fixed.",
                ]);
            }
        }

        return DB::transaction(function () use (
            $event,
            $region,
            $available,
            $selectedKeys,
            $teamPrefix,
            $expectedPlayers,
            $actor
        ): array {
            $eventRegion = DB::table('event_regions')
                ->where('event_id', $event->id)
                ->where('region_id', $region->id)
                ->lockForUpdate()
                ->first();
            abort_unless($eventRegion, 404);

            $imported = [];
            foreach ($selectedKeys as $key) {
                $parsed = $available->get($key);
                $category = $this->findCategory($parsed['category'])
                    ?? Category::create(['name' => $parsed['category']]);
                $categoryEvent = CategoryEvent::firstOrCreate(
                    ['event_id' => $event->id, 'category_id' => $category->id],
                    [
                        'entry_fee' => 0,
                        'ordering' => ((int) CategoryEvent::where('event_id', $event->id)->max('ordering')) + 1,
                    ]
                );

                $team = Team::query()
                    ->where('region_id', $region->id)
                    ->where('category_event_id', $categoryEvent->id)
                    ->lockForUpdate()
                    ->first();

                if (! $team) {
                    $team = new Team([
                        'name' => trim($teamPrefix.' '.$parsed['category']),
                        'num_team_members' => $expectedPlayers,
                        'year' => $event->start_date?->format('Y') ?? now()->year,
                        'published' => false,
                        'region_id' => $region->id,
                        'category_event_id' => $categoryEvent->id,
                        'noProfile' => true,
                    ]);
                    if (Schema::hasColumn('teams', 'user_id')) {
                        $team->setAttribute('user_id', $actor->id);
                    }
                    if (Schema::hasColumn('teams', 'personal_team')) {
                        $team->setAttribute('personal_team', false);
                    }
                    $team->save();
                } else {
                    $team->forceFill(['noProfile' => true])->save();
                }

                $validationErrors = $this->rosters->validateImport($team, $parsed['players']);
                if ($validationErrors !== []) {
                    throw ValidationException::withMessages(['teams' => $validationErrors]);
                }

                $this->rosters->import($team, $parsed['players'], $actor);
                $imported[] = [
                    'key' => $key,
                    'category' => $parsed['category'],
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'player_count' => count($parsed['players']),
                ];
            }

            activity('team-roster')->performedOn($event)->causedBy($actor)
                ->withProperties([
                    'event_id' => $event->id,
                    'region_id' => $region->id,
                    'team_ids' => array_column($imported, 'team_id'),
                    'team_keys' => $selectedKeys,
                ])
                ->log('External team workbook imported');

            return $imported;
        });
    }

    private function findCategory(string $name): ?Category
    {
        $exact = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($exact) {
            return $exact;
        }

        $targetKey = $this->categoryKey($name);

        return $targetKey
            ? Category::query()->get()->first(
                fn (Category $category): bool => $this->categoryKey($category->name) === $targetKey
            )
            : null;
    }

    private function categoryKey(string $name): ?string
    {
        $normalized = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name);
        $gender = preg_match('/\b(seuns|boy|boys)\b/', $normalized)
            ? 'boys'
            : (preg_match('/\b(dogters|girl|girls)\b/', $normalized) ? 'girls' : null);
        $ageText = preg_replace('/([uo0])(?=1[0-9])/', ' ', $normalized);

        if (! $gender || ! preg_match('/\b(1[0-9])\b/', $ageText, $ageMatch)) {
            return null;
        }

        return $gender.'-u'.((int) $ageMatch[1]);
    }
}
