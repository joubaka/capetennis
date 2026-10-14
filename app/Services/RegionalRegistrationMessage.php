<?php

namespace App\Services;

use App\Models\{Event, EventRegion, User};
use App\Services\Clothing\ClothingPriceService;
use Illuminate\Validation\ValidationException;

final class RegionalRegistrationMessage
{
    public function regions(Event $event, User $actor)
    {
        abort_unless($event->isTeam(), 404);
        return app(EventCommunicationService::class)->regions($event, $actor);
    }

    public function context(Event $event, User $actor, array $options): array
    {
        $region = $this->regions($event, $actor)->firstWhere('id', (int) ($options['event_region_id'] ?? 0));
        abort_unless($region, 403);
        $teams = app(EventTeamScope::class)->query($event)->where('region_id', $region->region_id)
            ->where('published', true)->with(['category', 'team_players', 'team_players_no_profile'])->orderBy('id')->get();
        $roles = app(SelectedTeamRosterStatus::class)->classify($event, $teams);
        $rows = app(EventCommunicationService::class)->rosterEntries($event, [$region->region_id], true)
            ->whereIn('team_id', $teams->pluck('id'))->filter(function (array $row) use ($teams, $roles): bool {
                $team = $teams->firstWhere('id', $row['team_id']);
                if (str_starts_with($row['key'], 'player:')) {
                    $id = (int) substr($row['key'], 7);
                    $slot = $team->team_players->firstWhere('player_id', $id);
                    return $slot && (int) $slot->rank > 0
                        && (!$team->num_team_members || (int) $slot->rank <= (int) $team->num_team_members)
                        && !isset($roles['profile'][$team->id][$id]);
                }
                $id = (int) substr($row['key'], 9);
                $slot = $team->team_players_no_profile->firstWhere('id', $id);
                return $slot && (int) $slot->rank > 0
                    && (!$team->num_team_members || (int) $slot->rank <= (int) $team->num_team_members)
                    && !isset($roles['imported'][$team->id][$id]);
            })->values();
        $clothing = [];
        $available = $region->region->usesOnlineClothingOrders() && (bool) $region->region->clothing_order;
        if (! empty($options['include_clothing'])) {
            if (! $available) {
                throw ValidationException::withMessages(['include_clothing' => 'Clothing ordering is not open for this region.']);
            }
            $items = $region->region->clothingItems()->with('sizes')->orderBy('ordering')->orderBy('id')->get();
            foreach ($items as $item) {
                if ((float) $item->price <= 0 || $item->sizes->isEmpty()) continue;
                $clothing[] = ['id' => $item->id, 'name' => $item->item_type_name,
                    'price' => $item->final_amount !== null ? (float) $item->final_amount
                        : app(ClothingPriceService::class)->totals((float) $item->price)['total'],
                    'sizes' => $item->sizes->map(fn ($size) => ['id' => $size->id, 'name' => $size->size])->all()];
            }
            if (! $clothing) throw ValidationException::withMessages(['include_clothing' => 'Check the region catalogue: no orderable items and sizes are available.']);
        }
        $fingerprint = hash('sha256', json_encode([
            $event->id, $region->id, $region->region->region_name,
            (bool) $event->published, app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->registrationIsOpen($event),
            $event->registrationClosesAt()?->toIso8601String(),
            $teams->map(fn ($team) => [$team->id, $team->name, $team->published, $team->num_team_members,
                $team->team_players->map(fn ($slot) => [$slot->id, $slot->player_id, $slot->rank])->all(),
                $team->team_players_no_profile->map(fn ($slot) => [$slot->id, $slot->player_profile, $slot->rank])->all()])->all(), $roles,
            $rows->all(), $available, $clothing,
        ], JSON_THROW_ON_ERROR));
        return compact('region', 'rows', 'clothing', 'available', 'fingerprint');
    }

    public function draft(Event $event, User $actor, array $options): array
    {
        $context = $this->context($event, $actor, $options);
        $name = $context['region']->region->region_name;
        $body = "Dear players and parents,\n\nCongratulations on being selected for {$name} for {$event->name}!\n\nRegistration is now open. Please visit the event page, find your player in the {$name} team and click Register to complete registration and payment.\n\nRegistration closing date and deadline:\nClothing orders closing date and deadline:\n\n".route('events.show', $event);
        if ($context['clothing']) {
            $body .= "\n\nClothing orders are open for {$name}. On the event page, choose Order clothing beside your player, select the items and sizes you need, and complete payment.\n";
            $body .= "\nClothing catalogue (customer prices):\n";
            foreach ($context['clothing'] as $item) $body .= $item['name'].' — R'.number_format($item['price'], 2).'; sizes: '.implode(', ', array_column($item['sizes'], 'name'))."\n";
        }
        return ['subject' => $event->name.' — Congratulations '.$name.'! Registration is open',
            'body' => $this->synchronizeDeadlines($body."\n\nKind regards,\nCape Tennis", $options),
            'registration_fingerprint' => $context['fingerprint']];
    }

    public function audience(Event $event, User $actor, array $options): array
    {
        $context = $this->context($event, $actor, $options);
        if (! app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->registrationIsOpen($event)) throw ValidationException::withMessages(['preview' => 'Event registration is closed. Publish the event and open registration before reviewing a registration-open email.']);
        if (! hash_equals($context['fingerprint'], $options['registration_fingerprint'] ?? '')) {
            throw ValidationException::withMessages(['preview' => 'The roster, contacts, registration settings or clothing catalogue changed. Update the suggested email and review again.']);
        }
        return $context['rows']->all();
    }

    private function formatDeadline(string $date, ?string $time): string
    {
        $label = \Carbon\Carbon::parse($date, 'Africa/Johannesburg')->format('d F Y');
        return $time ? $label.' at '.$time.' (South African time)' : $label;
    }

    /** Update only the managed deadline lines, preserving the organiser's edits. */
    public function synchronizeDeadlines(string $body, array $options): string
    {
        foreach ([
            ['registration_deadline', 'Registration closing date and deadline', '/^Registration (?:deadline|closing date and deadline):[^\r\n]*/mi'],
            ['clothing_deadline', 'Clothing orders closing date and deadline', '/^Clothing (?:order deadline|orders closing date and deadline):[^\r\n]*/mi'],
        ] as [$field, $label, $pattern]) {
            $date = $options[$field] ?? null;
            $line = $date ? $label.': '.$this->formatDeadline($date, $options[$field.'_time'] ?? null) : '';
            $found = false;
            $body = preg_replace_callback($pattern, function () use (&$found, $line): string {
                $replacement = $found ? '' : $line;
                $found = true;
                return $replacement;
            }, $body);
            if (! $found && $line !== '') {
                $signature = '/\r?\n\r?\n(?=Kind regards,)/i';
                $body = preg_match($signature, $body)
                    ? preg_replace_callback($signature, fn () => "\n\n".$line."\n\n", $body, 1)
                    : rtrim($body)."\n\n".$line;
            }
        }

        return $body;
    }
}
