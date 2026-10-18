<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\TeamPlayer;
use Illuminate\Database\Eloquent\Builder;

final class ImportedRosterProfileMatchService
{
    public function participants(Event $source): Builder
    {
        return Player::query()->where(function (Builder $query) use ($source): void {
            $query->whereHas('teams.category', fn ($q) => $q->where('event_id', $source->id))
                ->orWhereIn('players.id', NoProfileTeamPlayer::query()->select('player_profile')
                    ->whereHas('team.category', fn ($q) => $q->where('event_id', $source->id)))
                ->orWhereHas('registrations.categoryEvents', fn ($q) => $q->where('event_id', $source->id)
                    ->whereNull('category_event_registrations.deleted_at')
                    ->whereNull('category_event_registrations.withdrawn_at')
                    ->where(fn ($status) => $status->whereNull('category_event_registrations.status')
                        ->orWhere('category_event_registrations.status', '!=', 'pending_checkout')));
        });
    }

    public function rosterQuery(Team|TeamRegion $scope, Event $destination): Builder
    {
        return NoProfileTeamPlayer::query()->whereHas('team', fn ($q) => $q->where('noProfile', true)
            ->whereHas('category', fn ($category) => $category->where('event_id', $destination->id))
            ->when($scope instanceof Team, fn ($team) => $team->whereKey($scope->id), fn ($team) => $team->where('region_id', $scope->id)));
    }

    public function pageSize(Team|TeamRegion $scope): int
    {
        return $scope instanceof TeamRegion ? 100 : 25;
    }

    public function preview(Team|TeamRegion $scope, Event $source, int $page, ?Event $destination = null): array
    {
        $destination ??= $scope->category->event;
        $slots = $this->rosterQuery($scope, $destination)->with('team.category.category')->orderBy('team_id')->orderBy('rank')->orderBy('id')
            ->paginate($this->pageSize($scope), ['*'], 'roster_page', $page)->withQueryString();
        $identityCounts = $slots->getCollection()->mapWithKeys(fn ($slot) => [$this->identityKey($slot->name, $slot->surname) => 0])->all();
        foreach ($this->rosterQuery($scope, $destination)->select(['id', 'name', 'surname'])->lazyById(200) as $scopeSlot) {
            $key = $this->identityKey($scopeSlot->name, $scopeSlot->surname);
            if (array_key_exists($key, $identityCounts)) $identityCounts[$key]++;
        }
        $rows = $slots->getCollection()->map(function (NoProfileTeamPlayer $slot) use ($source, $scope, $destination, $identityCounts): array {
            $candidates = collect();
            $rawCandidateCount = 0;
            if (! $slot->player_profile && filled($slot->name) && filled($slot->surname)) {
                $normalize = fn ($name) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
                $rawCandidates = $this->participants($source)
                    ->where(function ($query) use ($slot, $normalize): void {
                        $query->where('identity_name_hash', hash('sha256', $normalize($slot->name).'|'.$normalize($slot->surname)))
                            ->orWhere(fn ($q) => $q->whereRaw('LOWER(TRIM(players.name)) = ?', [$normalize($slot->name)])
                                ->whereRaw('LOWER(TRIM(players.surname)) = ?', [$normalize($slot->surname)]));
                    })
                    ->orderBy('players.id')->limit(21)->get(['players.id', 'players.name', 'players.surname', 'players.dateOfBirth', 'players.email', 'players.gender']);
                $rawCandidateCount = $rawCandidates->count();
                $candidates = $rawCandidates
                    ->filter(fn ($player) => $normalize($player->name) === $normalize($slot->name) && $normalize($player->surname) === $normalize($slot->surname))
                    ->each(function (Player $player) use ($source): void {
                        $player->setAttribute('source_teams', $player->teams()->whereHas('category', fn ($q) => $q->where('event_id', $source->id))
                            ->with('category.category')->limit(10)->get()->map(fn ($team) => $team->name.' · '.$team->category?->category?->name)->all());
                        $player->setAttribute('source_categories', $player->registrations()->whereHas('categoryEvents', fn ($q) => $q->where('event_id', $source->id))
                            ->with(['categoryEvents' => fn ($q) => $q->where('event_id', $source->id)->with('category')])->limit(10)->get()
                            ->flatMap(fn ($registration) => $registration->categoryEvents->map(fn ($category) => $category->category?->name))->filter()->unique()->values()->all());
                        $importedContexts = NoProfileTeamPlayer::query()->where('player_profile', $player->id)
                            ->whereHas('team.category', fn ($q) => $q->where('event_id', $source->id))
                            ->with('team.category.category')->limit(10)->get()
                            ->map(fn ($slot) => $slot->team->name.' · '.$slot->team->category?->category?->name)->all();
                        $player->setAttribute('source_teams', array_values(array_unique(array_merge($player->source_teams, $importedContexts))));
                    });
            }

            $overflow = $rawCandidateCount > 20;
            $candidates = $candidates->take(20);
            $warnings = [];
            $duplicateRosterCount = 0;
            if (!$slot->player_profile) {
                if (blank($slot->name) || blank($slot->surname)) $warnings[] = 'Imported first name or surname is missing. Correct the roster identity before searching for profiles.';
                // Check the entire scope, including rows outside the current page.
                $duplicateRosterCount = $identityCounts[$this->identityKey($slot->name, $slot->surname)];
                if ($duplicateRosterCount > 1) $warnings[] = 'This name occurs more than once in this roster scope. Choose profiles carefully; no match is selected automatically.';
                foreach ($candidates as $candidate) {
                    $candidate->setAttribute('link_problems', $this->linkProblems($destination, $slot, $candidate));
                    $identityWarnings = [];
                    if (blank($candidate->dateOfBirth)) $identityWarnings[] = 'Profile date of birth is missing.';
                    if ($slot->date_of_birth && substr((string) $candidate->dateOfBirth, 0, 10) !== $slot->date_of_birth->format('Y-m-d')) $identityWarnings[] = 'Date of birth differs from the imported roster.';
                    if (filled($slot->email) && filled($candidate->email) && mb_strtolower(trim($slot->email)) !== mb_strtolower(trim($candidate->email))) $identityWarnings[] = 'Email differs from the imported roster.';
                    $categoryName = mb_strtolower((string) $slot->team->category?->category?->name);
                    $expectedGender = preg_match('/\b(boys?|seuns?)\b/u', $categoryName) ? 'male' : (preg_match('/\b(girls?|dogters?)\b/u', $categoryName) ? 'female' : null);
                    $gender = match (mb_strtolower(trim((string) $candidate->gender))) { '1', 'male', 'm', 'boy', 'boys' => 'male', '2', 'female', 'f', 'girl', 'girls' => 'female', default => null };
                    if (!$gender) $identityWarnings[] = 'Profile gender is missing or unrecognized.';
                    elseif ($expectedGender && $expectedGender !== $gender) $identityWarnings[] = 'Profile gender differs from this team category.';
                    $candidate->setAttribute('identity_warnings', $identityWarnings);
                }
            }
            $selectedPlayerId = !$slot->player_profile && !$overflow && !$warnings && $candidates->count() === 1
                && !$candidates->first()->link_problems && !$candidates->first()->identity_warnings ? $candidates->first()->id : null;

            return compact('slot', 'candidates', 'overflow', 'warnings', 'duplicateRosterCount', 'selectedPlayerId');
        });

        return compact('slots', 'rows');
    }

    private function identityKey(?string $name, ?string $surname): string
    {
        $normalize = fn ($value) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
        return $normalize($name).'|'.$normalize($surname);
    }

    public function fingerprint(array $preview, Event $event, Event $source, Team|TeamRegion $scope, int $actorId, int $page): string
    {
        $snapshot = $preview['rows']->map(fn ($row) => [
            $row['slot']->only(['id', 'team_id', 'name', 'surname', 'date_of_birth', 'email', 'rank', 'player_profile', 'pay_status']),
            $row['slot']->team->only(['id', 'category_event_id', 'region_id', 'noProfile']),
            $row['candidates']->map->getAttributes()->all(), $row['overflow'], $row['warnings'], $row['duplicateRosterCount'], $row['selectedPlayerId'],
        ])->all();

        return hash_hmac('sha256', json_encode([$event->id, $source->id, $scope::class, $scope->id, $actorId, $page, $snapshot], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    /** Read-only explanation; canonical relink independently rechecks under its locks. */
    private function linkProblems(Event $event, NoProfileTeamPlayer $slot, Player $player): array
    {
        $problems = [];
        $team = $slot->team;
        try { app(TeamSelectionInvitationService::class)->assertRosterEditable($team); }
        catch (\Illuminate\Validation\ValidationException $exception) { $problems = array_merge($problems, $exception->validator->errors()->all()); }
        try { app(\App\Services\PlayerEligibilityService::class)->assertEligible($player, $event); }
        catch (\RuntimeException $exception) { $problems[] = 'This profile is currently ineligible for the event.'; }
        $mirrors = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $slot->rank)->get();
        $mirror = $mirrors->first();
        if ($mirrors->count() > 1 || ($mirror && !in_array((int) $mirror->player_id, [0, (int) $player->id], true))
            || NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $slot->rank)->count() !== 1) $problems[] = 'Roster position is inconsistent. Repair it before linking.';
        if ((int) $slot->pay_status !== 0 || (int) $mirror?->pay_status !== 0) $problems[] = 'This roster position has payment history.';
        if (TeamPlayer::whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->where('player_id', $player->id)
                ->when($mirror && (int) $mirror->player_id === (int) $player->id, fn ($q) => $q->where('id', '!=', $mirror->id))->exists()
            || NoProfileTeamPlayer::whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))->where('player_profile', $player->id)->exists()) $problems[] = 'This profile is already assigned to a roster in this event.';
        if (\App\Models\TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory([$player->id])->exists()
            || \App\Models\ClothingOrder::where('event_id', $event->id)->where('player_id', $player->id)->exists()
            || \App\Models\TeamSelectionInvitation::where('event_id', $event->id)->where('player_id', $player->id)->exists()
            || \App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($q) => $q->where('players.id', $player->id))->exists()) $problems[] = 'This profile has existing payment, clothing, invitation or registration history in this event.';
        if (\App\Models\TeamFixturePlayer::whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
            ->where(fn ($q) => $q->where('team1_id', $player->id)->orWhere('team2_id', $player->id)
                ->orWhere('team1_no_profile_id', $slot->id)->orWhere('team2_no_profile_id', $slot->id))->exists()) $problems[] = 'Existing fixture participation protects this roster or profile.';
        return array_values(array_unique($problems));
    }
}
