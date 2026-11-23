<?php

namespace App\Services\Performance;

use App\Models\{CategoryEvent, Event, EventType, Player};
use App\Services\TeamDrawSideResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

/** Public discovery only: a stage match never grants registration or selection. */
class PlayerPathwayEvents
{
    public const CANDIDATE_LIMIT = 50;
    public const RESULT_LIMIT = 6;
    public const CATEGORY_LIMIT = 20;
    public const CONTEXT_LIMIT = 20;

    public function forPlayer(Player $player, array $pathway): array
    {
        $stage = $pathway['presentation']['event_stage'] ?? null;
        return $this->discover($stage, $player);
    }

    /** Anonymous exploration never loads a player's identity or saved evidence. */
    public function forStage(string $stage): array
    {
        return $this->discover($stage);
    }

    /** Category-specific labels only; an event can legitimately offer several stages. */
    public function badgesForEvent(Event $event): array
    {
        $labels = [];
        $budget = self::CONTEXT_LIMIT;
        $codeFamily = $this->family($event->eventTypeModel?->code);
        $nameFamily = $this->family($event->eventTypeModel?->name);
        if ($codeFamily && $nameFamily && $codeFamily !== $nameFamily) return [];
        $family = $codeFamily ?? $nameFamily;
        if ($event->isMasters() || $event->isInterprovincialTrials() || (int) $event->eventTypeModel?->type !== EventType::INDIVIDUAL) return [];
        foreach ($event->categoryEvents->take(self::CATEGORY_LIMIT) as $field) {
            $field->setRelation('event', $event);
            $classification = app(EventPathwayClassification::class);
            if ($classification->hasExplicit($field)) {
                if ($budget-- <= 0) break;
                $stage = $classification->forCategory($field);
                if ($stage !== null) $labels[] = ['category' => (string) $field->category?->name, 'stage' => $stage];
                continue;
            }
            foreach (['red', 'orange', 'Wilson B', 'Wilson A', 'TSA tournaments'] as $stage) {
                if ($budget <= 0) break 2;
                if ($this->categoryStage($field, $family, $stage, Schema::hasTable('draws'), $budget) === $stage) {
                    $labels[] = ['category' => (string) $field->category?->name, 'stage' => $stage];
                    break;
                }
            }
        }
        return $labels;
    }

    private function discover(?string $stage, ?Player $player = null): array
    {
        $result = ['stage' => $stage, 'events' => [], 'limited' => false];
        if (!$stage || !in_array($stage, ['red', 'orange', 'Wilson B', 'Wilson A', 'TSA tournaments'], true)) return $result;
        // Setup/test installations without the discovery schema can still show ratings.
        foreach (['events', 'eventtypes', 'category_events', 'categories'] as $table) {
            if (!Schema::hasTable($table)) return $result;
        }
        $now = CarbonImmutable::now('Africa/Johannesburg');
        $events = Event::query()->where('published', true)
            ->whereNotNull('start_date')
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $now->toDateString())
                ->orWhere(fn ($q) => $q->whereNull('end_date')->whereDate('start_date', '>=', $now->toDateString())))
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn(DB::raw('LOWER(TRIM(status))'),
                ['cancelled', 'canceled', 'archived', 'archive', 'completed', 'complete', 'postponed']))
            ->whereHas('eventTypeModel', fn ($q) => $q->where('type', EventType::INDIVIDUAL))
            ->where(fn ($q) => $q->whereNull('results_published')->orWhere('results_published', false))
            ->with(['eventTypeModel', 'categoryEvents' => fn ($q) => $q->with('category')->orderBy('id')->limit(self::CATEGORY_LIMIT + 1)])
            ->orderBy('start_date')->orderBy('id')->limit(self::CANDIDATE_LIMIT + 1)->get();
        $result['limited'] = $events->count() > self::CANDIDATE_LIMIT;
        $hasDraws = Schema::hasTable('draws');
        $hasVenues = Schema::hasTable('venues') && Schema::hasTable('event_venues');
        $remainingContexts = self::CONTEXT_LIMIT;
        foreach ($events->take(self::CANDIDATE_LIMIT) as $event) {
            if ($remainingContexts === 0) { $result['limited'] = true; break; }
            $result['limited'] = $result['limited'] || $event->categoryEvents->count() > self::CATEGORY_LIMIT;
            $codeFamily = $this->family($event->eventTypeModel?->code);
            $nameFamily = $this->family($event->eventTypeModel?->name);
            if ($codeFamily && $nameFamily && $codeFamily !== $nameFamily) continue;
            $type = $codeFamily ?? $nameFamily;
            // Invitation-only formats are never inferred to be ordinary stage events.
            if ($event->isMasters() || $event->isInterprovincialTrials()) continue;
            $categories = [];
            foreach ($event->categoryEvents->take(self::CATEGORY_LIMIT) as $field) {
                $field->setRelation('event', $event);
                if ($remainingContexts === 0) { $result['limited'] = true; break; }
                if (($player && !$this->compatible($player, $event, (string) $field->category?->name)) || $this->categoryStage($field, $type, $stage, $hasDraws, $remainingContexts) !== $stage) continue;
                $categories[] = (string) $field->category->name;
            }
            if (!$categories) continue;
            $venues = $hasVenues ? $event->venues()->orderBy('venues.id')->limit(3)->pluck('venues.name')->filter()->implode(', ') : '';
            $result['events'][] = ['id' => (int) $event->id, 'name' => $event->name,
                'url' => route('events.show', $event->id), 'date' => $event->start_date->format('d M Y'),
                'venue' => $venues ?: 'Venue to be confirmed', 'categories' => array_values(array_unique($categories)),
                'status' => $event->registrationIsOpen(\Illuminate\Support\Carbon::instance($now)) ? 'Entries open' : 'Entries closed'];
            if (count($result['events']) === self::RESULT_LIMIT) { $result['limited'] = true; break; }
        }
        return $result;
    }

    private function family(?string $label): ?string
    {
        return match (strtolower(trim((string) $label))) {
            'wilson', 'wilson-series', 'wilson series', 'wilson tournament', 'wilson tournaments' => 'wilson',
            'tsa', 'tsa-tournament', 'tsa-tournaments', 'tsa tournament', 'tsa tournaments' => 'tsa',
            default => null,
        };
    }

    private function categoryStage(CategoryEvent $field, ?string $family, string $wanted, bool $hasDraws, int &$remainingContexts): ?string
    {
        $classification = app(EventPathwayClassification::class);
        if ($classification->hasExplicit($field)) {
            $remainingContexts--;
            $declared = $classification->forCategory($field);
            return $declared === $wanted ? $declared : null;
        }
        $label = strtolower(trim((string) $field->category?->name));
        if ($label === '') return null;
        // Exact category words establish the candidate; event titles never do.
        preg_match_all('/\b(red|orange|green|yellow)\b/u', $label, $balls);
        $ballLevels = array_unique(array_map(fn ($ball) => in_array($ball, ['green', 'yellow'], true) ? 'standard' : $ball, $balls[1]));
        if (count($ballLevels) > 1) return null;
        $explicitBall = $ballLevels ? reset($ballLevels) : (BallRatingModel::isOrangeDivision($label) ? 'orange' : null);
        preg_match_all('/\b([abc])\b/u', $label, $divisions);
        $division = count(array_unique($divisions[1])) === 1 ? strtoupper($divisions[1][0]) : null;
        $wilson = $family === 'wilson' || preg_match('/\bwilson\s+[ab]\b/u', $label) === 1;
        if ($family === 'tsa' && $wilson) return null;
        $candidate = in_array($explicitBall, ['red', 'orange'], true) ? $explicitBall
            : ($family === 'tsa' ? 'TSA tournaments' : ($wilson && in_array($division, ['A', 'B'], true) ? 'Wilson '.$division : null));
        if ($candidate !== $wanted) return null;
        $remainingContexts--;
        // Respect canonical, event-scoped court metadata, including mixed/unknown contexts.
        $canonical = $hasDraws ? app(BallRatingContext::class)->stage($field)
            : BallRatingModel::forMatch(['category' => $label]);
        if ($canonical === null || ($explicitBall !== null && $canonical !== $explicitBall)) return null;
        if (in_array($canonical, ['red', 'orange'], true)) return $canonical;
        if ($family === 'tsa' && !$wilson) return 'TSA tournaments';
        return $wilson && in_array($division, ['A', 'B'], true) ? 'Wilson '.$division : null;
    }

    private function compatible(Player $player, Event $event, string $label): bool
    {
        $key = app(TeamDrawSideResolver::class)->categoryKey($label);
        $gender = match ((string) $player->gender) { '1', 'male' => 'boys', '2', 'female' => 'girls', default => null };
        if ($gender && $key['gender'] && $gender !== $key['gender']) return false;
        // Same calendar-year convention as alternate eligibility. Ambiguous or missing data stays a review item.
        $hasAge = preg_match_all('/\b(?:u\s*\/?\s*|under[\s-]*)(\d+)\b/iu', $label, $ages, PREG_OFFSET_CAPTURE) === 1;
        $afterAge = $hasAge ? substr($label, $ages[0][0][1] + strlen($ages[0][0][0])) : '';
        $ageRange = preg_match('/^\s*(?:[\/+-]|–|and|&)\s*\d+/iu', $afterAge) === 1;
        if ($player->dateOfBirth && $hasAge && !$ageRange) {
            try {
                $born = CarbonImmutable::parse($player->dateOfBirth);
                if ($born->isFuture() || $event->start_date->year - $born->year > (int) $ages[1][0][0]) return false;
            } catch (\Throwable) { /* Unknown birth date cannot establish admission. */ }
        }
        return true;
    }
}
