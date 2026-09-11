<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\EventRegion;
use App\Models\NoProfileTeamPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImportedRosterContactEnrichmentService
{
    public function preview(Event $event, EventRegion $eventRegion, array $parsedTeams): array
    {
        $slots = NoProfileTeamPlayer::query()
            ->whereHas('team', fn ($query) => $query->where('region_id', $eventRegion->region_id)
                ->whereHas('category', fn ($category) => $category->where('event_id', $event->id)))
            ->get(['id', 'team_id', 'name', 'surname', 'email', 'player_profile', 'pay_status', 'rank']);
        $slotsByName = $slots->groupBy(fn (NoProfileTeamPlayer $slot) => $this->nameKey($slot->name, $slot->surname));
        $updates = [];
        $issues = [];
        $unchanged = 0;
        $withoutEmail = 0;

        foreach ($parsedTeams as $team) {
            foreach ($team['players'] as $player) {
                $email = mb_strtolower(trim((string) ($player['email'] ?? '')));
                $label = trim($player['name'].' '.$player['surname']);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $withoutEmail++;
                    $issues[] = "{$label}: no valid workbook email was found, so this player will be left unchanged.";
                    continue;
                }

                $matches = $slotsByName->get($this->nameKey($player['name'], $player['surname']), collect());
                if ($matches->isEmpty()) {
                    $issues[] = "{$label}: no existing imported roster name matched.";
                    continue;
                }
                if ($matches->count() !== 1) {
                    $issues[] = "{$label}: matched more than one existing roster slot, so no email will be changed.";
                    continue;
                }

                $slot = $matches->first();
                $existing = mb_strtolower(trim((string) $slot->email));
                if ($existing !== '' && $existing !== $email) {
                    $issues[] = "{$label}: the existing email differs from the workbook, so it will be left unchanged.";
                    continue;
                }
                if ($existing === $email) {
                    $unchanged++;
                    continue;
                }

                $updates[] = ['slot_id' => $slot->id, 'name' => $label, 'email' => $email];
            }
        }

        $updates = collect($updates)->unique('slot_id')->sortBy('slot_id')->values()->all();
        $issues = array_values(array_unique($issues));
        $fingerprint = hash('sha256', json_encode([$event->id, $eventRegion->id, $updates, $issues]));

        return compact('updates', 'issues', 'unchanged', 'withoutEmail', 'fingerprint');
    }

    public function apply(Event $event, EventRegion $eventRegion, array $preview, string $fingerprint, User $actor): int
    {
        if (! hash_equals($preview['fingerprint'], $fingerprint)) {
            throw ValidationException::withMessages(['file' => 'The workbook or roster changed after preview. Review it again before importing.']);
        }

        return DB::transaction(function () use ($event, $eventRegion, $preview, $actor): int {
            $updated = 0;
            foreach ($preview['updates'] as $row) {
                $slot = NoProfileTeamPlayer::query()->lockForUpdate()->findOrFail($row['slot_id']);
                if (filled($slot->email)) continue;
                $slot->update(['email' => $row['email']]);
                $updated++;
            }

            activity('team-roster')->performedOn($eventRegion)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'region_id' => $eventRegion->region_id, 'email_count' => $updated])
                ->log('enriched imported roster emails from workbook');

            return $updated;
        });
    }

    private function nameKey(string $name, string $surname): string
    {
        $value = mb_strtolower(trim($name.' '.$surname));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
    }
}
