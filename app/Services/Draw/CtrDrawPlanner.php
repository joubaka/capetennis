<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Domain\Draws\Services\TennisScoreFormat;
use App\Models\{CategoryEvent, CtrDrawPlan, CtrEventCertification, Draw, DrawAuditLog, Event, PlatformAuditLog, User};
use App\Services\Performance\{BallRatingContext, PlayerAbilitySnapshotStore};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Private, event-bound preview/apply. This never rebuilds an existing draw. */
class CtrDrawPlanner
{
    public const VERSION = 3;
    public const MAX_GROUP_SIZE = 16;

    public function certify(Event $event, User $actor, bool $certified): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        abort_unless((int) $event->eventType === \App\Models\EventType::INDIVIDUAL, 422);
        app(CtrPlannerAccess::class)->authorize($actor, $event, false);
        $this->assertTransactional(['events', 'ctr_event_certifications', 'platform_audit_logs']);
        DB::transaction(function () use ($event, $actor, $certified) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $before = CtrEventCertification::where('event_id', $event->id)->first();
            if ($before && $before->certified === $certified) return;
            CtrEventCertification::updateOrCreate(['event_id' => $event->id], ['certified' => $certified, 'updated_by' => $actor->id]);
            PlatformAuditLog::create(['user_id' => $actor->id, 'action' => 'event.ctr_certification_changed',
                'subject_type' => 'Event', 'subject_id' => $event->id, 'before' => ['certified' => $before?->certified ?? false],
                'after' => ['certified' => $certified], 'created_at' => now()]);
        });
    }

    /** Prefer fours, add at most one extra per group, otherwise use threes. */
    public function sizes(int $count, int $target = 4, bool $tenCompetition = true): array
    {
        if ($count < 3) return [];
        $target = max(3, min(5, $target));
        if ($count === 10 && $target !== 5 && $tenCompetition) return [4, 3, 3];
        $groups = max(1, intdiv($count, $target));
        while ($count > $groups * 5) $groups++;
        while ($groups > 1 && $count < $groups * 3) $groups--;
        $base = intdiv($count, $groups);
        $extra = $count % $groups;
        return array_map(fn ($i) => $base + ($i < $extra ? 1 : 0), range(0, $groups - 1));
    }

    /** Projection contains only this event's eligible entrant identities and safe rating fields. */
    public function source(Event $event, User $actor, bool $lock = false): array
    {
        app(CtrPlannerAccess::class)->authorize($actor, $event);
        $categories = CategoryEvent::where('event_id', $event->id)->with('category')->orderBy('id');
        if ($lock) $categories->lockForUpdate();
        $categories = $categories->limit(257)->get();
        abort_if($categories->count() > 256, 422, 'This event exceeds the planner category limit.');
        // current() validates saved evidence/publication; no fitting or refresh on GET.
        $snapshot = $lock ? app(PlayerAbilitySnapshotStore::class)->freshCurrent() : app(PlayerAbilitySnapshotStore::class)->current();
        $rows = [];
        $excluded = 0;
        $assignment = app(GroupAssignmentService::class);
        foreach ($categories as $category) {
            $scope = new Draw(['event_id' => $event->id, 'category_event_id' => $category->id]);
            $scope->id = 0;
            $assigned = $assignment->assignedElsewhere($scope, $lock);
            $query = $assignment->eligible($scope)->with('registration.players')->orderBy('id');
            if ($lock) $query->lockForUpdate();
            $entrants = $query->limit(1601)->get();
            abort_if(count($rows) + $entrants->count() > 1600, 422, 'This event exceeds the planner player limit.');
            $pool = app(BallRatingContext::class)->stage($category);
            foreach ($entrants as $entrant) {
                if ($assigned->contains($entrant->registration_id)) { $excluded++; continue; }
                $players = $lock ? $entrant->registration->players()->lockForUpdate()->get() : $entrant->registration->players;
                $entrant->registration->setRelation('players', $players);
                $player = $players->count() === 1 ? $players->first() : null;
                $rating = $pool !== null && $player ? ($snapshot['ball_ratings'][$pool]['ratings'][$player->id] ?? []) : [];
                $valid = empty($snapshot['reason']) && empty($snapshot['snapshot_stale']) && $pool !== null
                    && $player && is_numeric($rating['score'] ?? null) && is_finite((float) $rating['score'])
                    && $rating['score'] >= 0 && $rating['score'] <= 100 && ($rating['comparable'] ?? false) === true
                    && !empty($rating['component']) && empty($rating['snapshot_stale']);
                $rows[] = ['entry_id' => $entrant->id, 'registration_id' => (int) $entrant->registration_id,
                    'player_id' => $player?->id, 'category_id' => $category->id, 'category' => $category->category?->name ?? 'Players',
                    'pool' => $pool, 'name' => $entrant->registration->display_name,
                    'score' => $valid ? (float) $rating['score'] : null,
                    'component' => $valid ? (string) $rating['component'] : null,
                    'confidence' => $valid ? (string) ($rating['confidence_label'] ?? 'Unspecified confidence') : null,
                    'historical_connection' => $valid && !empty($rating['historical_connection']),
                    'rating_label' => $valid ? 'CTR' : (empty($rating) && empty($snapshot['reason']) && empty($snapshot['snapshot_stale']) ? 'Unrated' : 'CTR unavailable'),
                    'issue' => !$player ? 'Singles registration must identify exactly one player.' : ($pool === null ? 'Ball level needs review.' : null),
                    'eligibility' => $entrant->only(['payment_status_id', 'withdrawn_at', 'status']),
                ];
            }
        }
        $drawQuery = Draw::where('event_id', $event->id)->orderBy('id');
        if ($lock) $drawQuery->lockForUpdate();
        $existing = $drawQuery->get(['id', 'category_event_id', 'drawName', 'locked', 'published']);
        $fingerprint = hash('sha256', json_encode([self::VERSION, $event->only(['id', 'eventType']),
            CtrEventCertification::where('event_id', $event->id)->first()?->getAttributes(),
            $categories->map(fn ($category) => [$category->getAttributes(), $category->category?->getAttributes()])->all(),
            $rows, $excluded, $existing->toArray(), $snapshot['built_at'] ?? null, $snapshot['snapshot_as_of'] ?? null,
            $snapshot['snapshot_version'] ?? null, $snapshot['snapshot_stale'] ?? null, $snapshot['reason'] ?? null,
        ], JSON_THROW_ON_ERROR));
        return ['rows' => $rows, 'excluded' => $excluded, 'existing' => $existing, 'fingerprint' => $fingerprint];
    }

    public function preview(Event $event, User $actor, int $target = 4, array $categoryGroups = []): CtrDrawPlan
    {
        // Fingerprint persisted scalar types just as the apply-time fresh read does.
        $source = $this->source($event->fresh(), $actor);
        if (array_diff(array_keys($categoryGroups), array_column($source['rows'], 'category_id'))) {
            $this->invalid('Choose grouping only for available categories in this event.');
        }
        foreach ($categoryGroups as $mode) {
            if (!in_array($mode, ['auto', 'one', 'two'], true)) $this->invalid('Choose automatic, one group or two groups.');
        }
        $rows = [];
        $straightBlocks = [];
        foreach (collect($source['rows'])->groupBy('category_id') as $categoryId => $categoryRows) {
            $mode = $categoryGroups[$categoryId] ?? 'auto';
            $requestedGroups = $mode === 'auto' ? null : ($mode === 'one' ? 1 : 2);
            if ($requestedGroups && ($categoryRows->count() < $requestedGroups * 3 || $categoryRows->count() > $requestedGroups * self::MAX_GROUP_SIZE)) {
                $this->invalid($categoryRows->first()['category'].': '.($requestedGroups === 1 ? 'One group needs 3–16 players.' : 'Two groups need 6–32 players.').' Choose automatic grouping instead.');
            }
            if ($mode === 'auto' && ($categoryRows->count() === 6 || ($categoryRows->count() === 10 && $target !== 5))) {
                $straightBlocks[] = ['category_id' => (int) $categoryRows->first()['category_id'],
                    'groups' => $categoryRows->count() === 6 ? [1, 2] : [2, 3]];
            }
            $number = 0;
            $rated = $categoryRows->whereNotNull('score')->groupBy('component')->sortKeys();
            $unknown = $categoryRows->whereNull('score')->sortBy('entry_id')->values();
            // A single comparable scale can be grouped with unknowns as a suffix.
            // Multiple scales stay in separate bands; their numbers are never ranked.
            $bands = [];
            foreach ($rated as $componentRows) {
                $bands[] = $componentRows->sort(fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['player_id'] <=> $b['player_id']))->values();
            }
            if (count($bands) <= 1) {
                $bands = [($bands[0] ?? collect())->concat($unknown)->values()];
            } elseif ($unknown->isNotEmpty()) {
                $bands[] = $unknown;
            }
            $bandLabels = [];
            foreach ($bands as $bandIndex => $band) {
                foreach ($band as $row) {
                    $bandLabels[$row['entry_id']] = count($rated) > 1
                        ? ($row['score'] === null ? 'Unrated starting group' : 'Comparison band '.($bandIndex + 1))
                        : null;
                }
            }
            // Explicit layouts retain band order and labels without comparing scores across scales.
            if ($requestedGroups) $bands = [collect($bands)->flatMap(fn ($band) => $band->all())->values()];
            foreach ($bands as $bandIndex => $ordered) {
                $offset = 0;
                $sizes = $requestedGroups
                    ? array_map(fn ($i) => intdiv($ordered->count(), $requestedGroups) + ($i < $ordered->count() % $requestedGroups ? 1 : 0), range(0, $requestedGroups - 1))
                    : ($this->sizes($ordered->count(), $target, $categoryRows->count() === 10) ?: [$ordered->count()]);
                foreach ($sizes as $size) {
                    $number++;
                    foreach ($ordered->slice($offset, $size) as $row) {
                        $row['group'] = $number;
                        $row['order'] = count($rows);
                        $row['band'] = $bandLabels[$row['entry_id']];
                        $rows[] = $row;
                    }
                    $offset += $size;
                }
            }
        }
        foreach ($straightBlocks as $block) {
            $indices = array_keys(array_filter($rows, fn ($row) => (int) $row['category_id'] === $block['category_id'] && in_array($row['group'], $block['groups'], true)));
            $members = collect($indices)->map(fn ($index) => $rows[$index]);
            // Only one comparable scale can establish rank order. Unknowns remain
            // a deterministic suffix; different scales require explicit review.
            if (count($indices) === 6 && $members->pluck('component')->filter()->unique()->count() <= 1) {
                foreach ($indices as $position => $index) $rows[$index]['group'] = $block['groups'][in_array($position, [0,3,4], true) ? 0 : 1];
            }
        }
        return CtrDrawPlan::create(['token' => (string) Str::uuid(), 'event_id' => $event->id, 'actor_id' => $actor->id,
            'fingerprint' => $source['fingerprint'], 'proposal' => ['version' => self::VERSION, 'target' => $target, 'category_groups' => $categoryGroups, 'rows' => $rows, 'excluded_category_ids' => [], 'straight_blocks' => $straightBlocks,
                'manual_reviewed' => false, 'short_matches_reviewed' => false, 'scoring_required' => true], 'expires_at' => now()->addMinutes(30)])->fresh();
    }

    public function revision(CtrDrawPlan $plan): string
    {
        return hash('sha256', json_encode($plan->proposal, JSON_THROW_ON_ERROR));
    }

    public function owned(Event $event, User $actor, string $token, bool $lock = false): CtrDrawPlan
    {
        app(CtrPlannerAccess::class)->authorize($actor, $event);
        $query = CtrDrawPlan::where('token', $token)->where('event_id', $event->id)->where('actor_id', $actor->id);
        if ($lock) $query->lockForUpdate();
        return $query->firstOrFail();
    }

    /** Bounded, unordered partitions: every player is retained, each group has 3–16. */
    public function layouts(int $count): array
    {
        if ($count < 3 || $count > 64) return [];
        $options = [];
        $visit = function (int $remaining, int $minimum, array $sizes) use (&$visit, &$options) {
            if (count($options) >= 100) return;
            if ($remaining === 0) { $options[] = $sizes; return; }
            for ($size = $minimum; $size <= min(self::MAX_GROUP_SIZE, $remaining); $size++) {
                if ($remaining !== $size && $remaining - $size < $size) continue;
                $visit($remaining - $size, $size, [...$sizes, $size]);
            }
        };
        $visit($count, 3, []);
        return $options;
    }

    public function review(Event $event, User $actor, string $token, string $revision, array $placements, bool $manual, array $excludedCategories = [], bool $shortMatches = false, ?int $regroupCategory = null, ?string $groupSizes = null, ?array $scoring = null): CtrDrawPlan
    {
        return DB::transaction(function () use ($event, $actor, $token, $revision, $placements, $manual, $excludedCategories, $shortMatches, $regroupCategory, $groupSizes, $scoring) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $plan = $this->owned($event, $actor, $token, true);
            $this->assertCurrent($event, $actor, $plan, $revision);
            $proposal = $plan->proposal;
            $expected = array_map('strval', array_column($proposal['rows'], 'entry_id'));
            $keys = array_map('strval', array_keys($placements)); sort($keys); sort($expected);
            if ($keys !== $expected) $this->invalid('Every proposed player must be included exactly once.');
            foreach ($proposal['rows'] as &$row) $row['group'] = (int) $placements[$row['entry_id']];
            unset($row);
            $proposal['manual_reviewed'] = $manual;
            $proposal['excluded_category_ids'] = array_values(array_unique(array_map('intval', $excludedCategories)));
            $proposal['short_matches_reviewed'] = $shortMatches;
            if ($scoring !== null && empty($scoring['score_format'])) unset($proposal['scoring']);
            if ($scoring !== null && !empty($scoring['score_format'])) {
                if (!in_array($scoring['score_format'], TennisScoreFormat::keys(), true)
                    || !in_array($scoring['playoff_score_format'] ?? $scoring['score_format'], TennisScoreFormat::keys(), true)
                    || !isset($scoring['match_duration_minutes'], $scoring['changeover_minutes'])
                    || filter_var($scoring['match_duration_minutes'], FILTER_VALIDATE_INT) === false
                    || filter_var($scoring['changeover_minutes'], FILTER_VALIDATE_INT) === false
                    || $scoring['match_duration_minutes'] < 1 || $scoring['match_duration_minutes'] > 240
                    || $scoring['changeover_minutes'] < 0 || $scoring['changeover_minutes'] > 60) {
                    $this->invalid('Choose a supported scoring format, match duration of 1–240 minutes and changeover of 0–60 minutes.');
                }
                $proposal['scoring'] = ['score_format' => $scoring['score_format'],
                    'playoff_score_format' => $scoring['playoff_score_format'] ?? $scoring['score_format'],
                    'num_sets' => TennisScoreFormat::maxSets($scoring['score_format']),
                    'match_duration_minutes' => (int) $scoring['match_duration_minutes'], 'changeover_minutes' => (int) $scoring['changeover_minutes']];
            }
            if ($regroupCategory !== null) {
                $members = collect($proposal['rows'])->where('category_id', $regroupCategory)->sortBy('order')->values();
                if ($members->isEmpty() || in_array($regroupCategory, $proposal['excluded_category_ids'], true)) {
                    $this->invalid('Choose an included category belonging to this exact plan.');
                }
                if (!preg_match('/^\s*\d+(?:\s*[, +]\s*\d+)*\s*$/', $groupSizes ?? '')) {
                    $this->invalid('Enter group sizes separated by + or commas, for example 4+4+8.');
                }
                $sizes = array_map('intval', preg_split('/\s*[, +]\s*/', trim($groupSizes)));
                if (array_sum($sizes) !== $members->count() || min($sizes) < 3 || max($sizes) > self::MAX_GROUP_SIZE) {
                    $this->invalid('Group sizes must use every player in this category exactly once, with 3–16 per group.');
                }
                $assignments = [];
                $offset = 0;
                foreach ($sizes as $number => $size) {
                    foreach ($members->slice($offset, $size) as $member) $assignments[$member['entry_id']] = $number + 1;
                    $offset += $size;
                }
                foreach ($proposal['rows'] as &$row) {
                    if ((int) $row['category_id'] === $regroupCategory) $row['group'] = $assignments[$row['entry_id']];
                }
                unset($row);
                $proposal['straight_blocks'] = array_values(array_filter($proposal['straight_blocks'] ?? [], fn ($block) => (int) $block['category_id'] !== $regroupCategory));
            }
            $this->validateProposal($proposal, false);
            $plan->update(['proposal' => $proposal]);
            return $plan->fresh();
        });
    }

    public function groups(array $proposal): array
    {
        $groups = [];
        foreach ($proposal['rows'] as $row) {
            if (!$row['group']) continue;
            $key = $row['category_id'].':'.$row['group'];
            $groups[$key] ??= ['name' => $row['category'].' · Group '.$row['group'], 'category_id' => $row['category_id'], 'number' => (int) $row['group'], 'rows' => []];
            $groups[$key]['rows'][] = $row;
        }
        foreach ($groups as &$group) {
            // Order seeds by CTR only when all members share a comparable scale.
            $members = collect($group['rows']);
            if ($members->pluck('component')->filter()->unique()->count() <= 1) {
                $group['rows'] = $members->sort(function ($a, $b) {
                    if (($a['score'] === null) !== ($b['score'] === null)) return $a['score'] === null ? 1 : -1;
                    return ($a['score'] === null ? 0 : ($b['score'] <=> $a['score'])) ?: ($a['entry_id'] <=> $b['entry_id']);
                })->values()->all();
            } else {
                $group['rows'] = $members->sort(fn ($a, $b) => (($a['score'] === null) <=> ($b['score'] === null))
                    ?: (($a['order'] ?? $a['entry_id']) <=> ($b['order'] ?? $b['entry_id'])))->values()->all();
            }
        }
        unset($group);
        return collect($groups)->sort(fn ($a, $b) => ($a['category_id'] <=> $b['category_id']) ?: ($a['number'] <=> $b['number']))->values()->all();
    }

    public function competitions(array $proposal): array
    {
        $competitions = [];
        $excluded = $proposal['excluded_category_ids'] ?? [];
        foreach (collect($this->groups($proposal))->groupBy('category_id') as $categoryId => $groups) {
            if (in_array((int) $categoryId, $excluded, true)) continue;
            $used = [];
            foreach ($proposal['straight_blocks'] ?? [] as $block) {
                if ((int) $block['category_id'] !== (int) $categoryId) continue;
                $blockGroups = $groups->whereIn('number', $block['groups'])->values();
                $used = array_merge($used, $block['groups']);
                $competitions[] = ['name' => $groups->first()['rows'][0]['category'].' · Six-player competition',
                    'category_id' => (int) $categoryId, 'rows' => $blockGroups->flatMap(fn ($group) => $group['rows'])->all(),
                    'groups' => $blockGroups->all(), 'six' => true, 'matches' => 9, 'number' => min($block['groups'])];
            }
            foreach ($groups->whereNotIn('number', $used) as $group) $competitions[] = $group + ['groups' => [$group], 'six' => false,
                'matches' => count($group['rows']) * (count($group['rows']) - 1) / 2];
        }
        return collect($competitions)->sort(fn ($a, $b) => ($a['category_id'] <=> $b['category_id']) ?: ($a['number'] <=> $b['number']))->values()->all();
    }

    public function validateProposal(array $proposal, bool $requireScoring = true): void
    {
        if (empty($proposal['rows'])) $this->invalid('There are no available players to create draws for.');
        if (($proposal['version'] ?? null) !== self::VERSION) $this->invalid('This preview uses an earlier grouping rule. Press Plan all draws for a fresh preview.');
        $excluded = $proposal['excluded_category_ids'] ?? [];
        if (array_diff($excluded, array_column($proposal['rows'], 'category_id'))) $this->invalid('Exclude only categories belonging to this exact plan.');
        foreach ($proposal['rows'] as $row) {
            if (in_array((int) $row['category_id'], $excluded, true)) continue;
            if (!$row['player_id'] || $row['pool'] === null) $this->invalid('Resolve the singles identity or ball-level issue before creating draws.');
            if ($row['group'] < 1 || $row['group'] > 1600) $this->invalid('Assign every player to a group before confirming.');
        }
        foreach ($this->competitions($proposal) as $competition) {
            if ($competition['six'] && (count($competition['groups']) !== 2 || collect($competition['groups'])->contains(fn ($group) => count($group['rows']) !== 3))) {
                $this->invalid('Six players need exactly two groups of three for the straight-over finals.');
            }
            if ($competition['six'] && collect($competition['rows'])->pluck('component')->filter()->unique()->count() > 1 && !$proposal['manual_reviewed']) {
                $this->invalid('Review placements across different CTR comparison bands before creating this linked competition.');
            }
            foreach ($competition['groups'] as $group) {
                $count = count($group['rows']);
                if ($count < 3 || $count > self::MAX_GROUP_SIZE) $this->invalid('Not enough players, or too many in one group. Each included group needs 3–16 players.');
                if (!$competition['six'] && $count === 3 && !($proposal['short_matches_reviewed'] ?? false)) {
                    $this->invalid('Three-player groups have only two matches per player, below the target of three. Review and explicitly accept this exception, or rebalance or exclude the category.');
                }
                if (collect($group['rows'])->pluck('component')->filter()->unique()->count() > 1 && !$proposal['manual_reviewed']) {
                    $this->invalid('Review manual placements before mixing players from different rating comparison groups.');
                }
            }
            $ids = array_column($competition['rows'], 'player_id');
            if (count($ids) !== count(array_unique($ids))) $this->invalid('A player cannot occur twice in one competition.');
        }
        // Aliases cannot silently place a real player in two draws of the same category.
        foreach (collect($proposal['rows'])->groupBy('category_id') as $rows) {
            if (in_array((int) $rows->first()['category_id'], $excluded, true)) continue;
            if ($rows->pluck('player_id')->unique()->count() !== $rows->count()) $this->invalid('Duplicate player registrations need review before planning.');
        }
        if (!$this->competitions($proposal)) $this->invalid('Include at least one category with enough players to create draws.');
        if ($requireScoring && ($proposal['scoring_required'] ?? false) && empty($proposal['scoring'])) {
            $this->invalid('Choose and save scoring and match timing before creating draws.');
        }
    }

    public function apply(Event $event, User $actor, string $token, string $revision): CtrDrawPlan
    {
        $this->assertTransactional();
        return DB::transaction(function () use ($event, $actor, $token, $revision) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $plan = $this->owned($event, $actor, $token, true);
            abort_unless(hash_equals($this->revision($plan), $revision), 409, 'The reviewed plan changed. Review it again.');
            if ($plan->applied_at) return $plan;
            $this->assertCurrent($event, $actor, $plan, $revision, true);
            $this->validateProposal($plan->proposal);
            $ids = [];
            foreach ($this->competitions($plan->proposal) as $competition) {
                $draw = Draw::create(['event_id' => $event->id, 'category_event_id' => $competition['category_id'],
                    'drawName' => $competition['name'], 'published' => 0, 'oop_published' => 0, 'locked' => 0, 'oop_created' => 0]);
                $six = $competition['six'];
                $draw->settings()->create(array_merge($plan->proposal['scoring'] ?? [], ['workflow' => $six ? 'round_robin_playoffs' : 'round_robin', 'boxes' => $six ? 2 : 1,
                    'draw_format_id' => \App\Models\DrawFormats::where('name', 'Round Robin')->value('id'),
                    'preset_key' => $six ? '2_groups_cross' : null,
                    'playoff_config' => $six ? array_slice(\App\Models\DrawSetting::getPresetTemplates()['2_groups_cross']['config'], 0, 3) : null]));
                $assignments = app(GroupAssignmentService::class);
                $placements = [];
                foreach ($competition['groups'] as $index => $plannedGroup) {
                    $group = $draw->groups()->create(['name' => chr(65 + $index), 'round_robin_phase' => 1]);
                    $placements[] = ['group_id' => $group->id, 'registration_ids' => array_column($plannedGroup['rows'], 'registration_id')];
                }
                $assignments->save($draw, ['revision' => $assignments->revision($draw), 'groups' => $placements]);
                app(RoundRobinGenerationService::class)->generate($draw);
                if ($six && app(AutomaticPlayoffPreparationService::class)->prepare($draw) !== 3) {
                    $this->invalid('The straight-over finals could not be prepared. No draws were created.');
                }
                DrawAuditLog::record($draw->id, 'ctr_programme_created', null, ['plan_token' => $plan->token,
                    'winner_prizes' => 1, 'sportsmanship_prizes' => 1, 'template_version' => self::VERSION,
                    'excluded_category_ids' => $plan->proposal['excluded_category_ids'] ?? [],
                    'short_matches_reviewed' => (bool) ($plan->proposal['short_matches_reviewed'] ?? false),
                    'manual_comparison_reviewed' => (bool) ($plan->proposal['manual_reviewed'] ?? false),
                    'scoring' => $plan->proposal['scoring'] ?? null,
                    'competition_format' => $six ? 'six_player_straight_finals' : 'independent_round_robin',
                    'planned_group_numbers' => array_column($competition['groups'], 'number')]);
                $ids[] = $draw->id;
            }
            $plan->update(['draw_ids' => $ids, 'applied_at' => now()]);
            return $plan->fresh();
        });
    }

    private function assertCurrent(Event $event, User $actor, CtrDrawPlan $plan, string $revision, bool $lock = false): void
    {
        abort_if($plan->applied_at || $plan->expires_at->isPast(), 409, 'This plan was already created or has expired. Plan again.');
        abort_unless(hash_equals($this->revision($plan), $revision), 409, 'The plan changed. Review it again.');
        abort_unless(hash_equals($plan->fingerprint, $this->source($event->fresh(), $actor, $lock)['fingerprint']), 409,
            'Players, ratings, certification or draws changed. Plan again before confirming.');
    }

    /** Legacy nontransactional tables cannot provide all-or-nothing creation. */
    public function assertTransactional(?array $tables = null): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $tables ??= ['events', 'category_events', 'category_event_registrations', 'ctr_event_certifications', 'ctr_draw_plans',
            'draws', 'draw_settings', 'draw_groups', 'draw_group_registrations', 'fixtures', 'draw_audit_logs', 'platform_audit_logs',
            'audit_events', 'draw_venues', 'draw_venue_court_allocations'];
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
        foreach ($engines as $engine) abort_unless(strtolower((string) $engine) === 'innodb', 409,
            'The CTR planner requires transactional draw tables. Ask an administrator to review the database before creating draws.');
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['groups' => $message]);
    }
}
