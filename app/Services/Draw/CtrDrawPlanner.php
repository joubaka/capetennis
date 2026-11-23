<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{CategoryEvent, CtrDrawPlan, CtrEventCertification, Draw, DrawAuditLog, Event, PlatformAuditLog, User};
use App\Services\Performance\{BallRatingContext, PlayerAbilitySnapshotStore};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Private, event-bound preview/apply. This never rebuilds an existing draw. */
class CtrDrawPlanner
{
    public const VERSION = 1;

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
    public function sizes(int $count, int $target = 4): array
    {
        if ($count < 3) return [];
        $target = max(3, min(5, $target));
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
                    'issue' => !$player ? 'Singles registration must identify exactly one player.' : ($pool === null ? 'Ball level needs review.'
                        : (!$valid ? 'CTR unavailable, stale or not comparable — place manually.' : null)),
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

    public function preview(Event $event, User $actor, int $target = 4): CtrDrawPlan
    {
        $source = $this->source($event, $actor);
        $rows = $source['rows'];
        $groups = [];
        foreach (collect($rows)->groupBy('category_id') as $categoryId => $categoryRows) {
            $number = 0;
            // Different comparison groups are never ranked against each other.
            foreach ($categoryRows->whereNotNull('score')->groupBy('component') as $componentRows) {
                $ordered = $componentRows->sort(fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['player_id'] <=> $b['player_id']))->values();
                $offset = 0;
                foreach ($this->sizes($ordered->count(), $target) as $size) {
                    $number++;
                    foreach ($ordered->slice($offset, $size) as $row) $groups[$row['entry_id']] = $number;
                    $offset += $size;
                }
            }
        }
        foreach ($rows as &$row) $row['group'] = $groups[$row['entry_id']] ?? 0;
        unset($row);
        return CtrDrawPlan::create(['token' => (string) Str::uuid(), 'event_id' => $event->id, 'actor_id' => $actor->id,
            'fingerprint' => $source['fingerprint'], 'proposal' => ['version' => self::VERSION, 'target' => $target, 'rows' => $rows,
                'manual_reviewed' => false], 'expires_at' => now()->addMinutes(30)]);
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

    public function review(Event $event, User $actor, string $token, string $revision, array $placements, bool $manual): CtrDrawPlan
    {
        return DB::transaction(function () use ($event, $actor, $token, $revision, $placements, $manual) {
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
            $this->validateProposal($proposal);
            $plan->update(['proposal' => $proposal]);
            return $plan->fresh();
        });
    }

    public function competitions(array $proposal): array
    {
        $groups = [];
        foreach ($proposal['rows'] as $row) {
            if (!$row['group']) continue;
            $key = $row['category_id'].':'.$row['group'];
            $groups[$key] ??= ['name' => $row['category'].' · Group '.$row['group'], 'category_id' => $row['category_id'], 'rows' => []];
            $groups[$key]['rows'][] = $row;
        }
        foreach ($groups as &$group) {
            // Order seeds by CTR only when all members share a comparable scale.
            $members = collect($group['rows']);
            if ($members->whereNull('score')->isEmpty() && $members->pluck('component')->unique()->count() === 1) {
                $group['rows'] = $members->sort(fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($a['player_id'] <=> $b['player_id']))->values()->all();
            }
        }
        unset($group);
        return array_values($groups);
    }

    public function validateProposal(array $proposal): void
    {
        if (empty($proposal['rows'])) $this->invalid('There are no available players to create draws for.');
        foreach ($proposal['rows'] as $row) {
            if (!$row['player_id'] || $row['pool'] === null) $this->invalid('Resolve the singles identity or ball-level issue before creating draws.');
            if ($row['group'] < 1 || $row['group'] > 1600) $this->invalid('Assign every player to a group before confirming.');
            if ($row['issue'] && !$proposal['manual_reviewed']) $this->invalid('Confirm that you reviewed manual placements for players without comparable CTR.');
        }
        foreach ($this->competitions($proposal) as $competition) {
            $count = count($competition['rows']);
            if ($count < 3 || $count > 5) $this->invalid('Each group must contain three to five players.');
            $ids = array_column($competition['rows'], 'player_id');
            if (count($ids) !== count(array_unique($ids))) $this->invalid('A player cannot occur twice in one competition.');
            if (collect($competition['rows'])->pluck('component')->filter()->unique()->count() > 1 && !$proposal['manual_reviewed']) {
                $this->invalid('Review manual placements before mixing players from different rating comparison groups.');
            }
        }
        // Aliases cannot silently place a real player in two draws of the same category.
        foreach (collect($proposal['rows'])->groupBy('category_id') as $rows) {
            if ($rows->pluck('player_id')->unique()->count() !== $rows->count()) $this->invalid('Duplicate player registrations need review before planning.');
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
                $draw->settings()->create(['workflow' => 'round_robin', 'boxes' => 1,
                    'draw_format_id' => \App\Models\DrawFormats::where('name', 'Round Robin')->value('id')]);
                $group = $draw->groups()->create(['name' => 'A', 'round_robin_phase' => 1]);
                $assignments = app(GroupAssignmentService::class);
                $assignments->save($draw, ['revision' => $assignments->revision($draw), 'groups' => [
                    ['group_id' => $group->id, 'registration_ids' => array_column($competition['rows'], 'registration_id')],
                ]]);
                app(RoundRobinGenerationService::class)->generate($draw);
                DrawAuditLog::record($draw->id, 'ctr_programme_created', null, ['plan_token' => $plan->token,
                    'winner_prizes' => 1, 'sportsmanship_prizes' => 1, 'template_version' => self::VERSION]);
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
