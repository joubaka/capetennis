<?php

namespace App\Services;

use App\Domain\Entries\Services\EntryService;
use App\Domain\Ranking\Services\RankingRebuildService;
use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\PlayerDuplicateDecision;
use App\Models\PlayerMergeAudit;
use App\Models\Series;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlayerDuplicateService
{
    private ?array $resolvedReferences = null;

    /** Columns that contain actual player IDs. Registration IDs are excluded. */
    public const PLAYER_REFERENCES = [
        'registration_order_items' => ['player_id'],
        'player_registrations' => ['player_id'],
        'team_players' => ['player_id'],
        'team_payment_orders' => ['player_id'],
        'transactions_pf' => ['player_id', 'custom_int2'],
        'player_subscriptions' => ['player_id'],
        'positions' => ['player_id'],
        'ranking_scores' => ['player_id'],
        'ranking_score_legs' => ['player_id'],
        'rankings' => ['player_id'],
        'series_rankings' => ['player_id'],
        'practices' => ['player_id'],
        'exercises' => ['player_id'],
        'invatations' => ['player_id'], // Legacy production spelling.
        'leaderboards' => ['player_id'],
        'goals' => ['player_id'],
        'clothing_orders' => ['player_id'],
        'player_agreements' => ['player_id'],
        'player_violations' => ['player_id'],
        'player_suspensions' => ['player_id'],
        'event_nominations' => ['player_id'],
        'team_fixture_players' => ['team1_id', 'team2_id'],
        'fixture_players' => ['team1_id', 'team2_id'],
        'team_fixture_results' => ['match_winner_id', 'match_loser_id'],
    ];

    /** Registration IDs are preserved; these rows follow the reassigned registration owner. */
    private const REGISTRATION_HISTORY_REFERENCES = [
        'category_event_registrations' => ['registration_id'],
        'category_results' => ['registration_id'],
        'fixtures' => ['registration1_id', 'registration2_id', 'winner_registration'],
        'fixture_results' => ['winner_registration', 'loser_registration'],
        'practice_fixtures' => ['registration1_id', 'registration2_id'],
        'practice_results' => ['winner_registration', 'loser_registration'],
    ];

    private const PROFILE_FIELDS = [
        'name', 'surname', 'dateOfBirth', 'gender', 'email', 'cellNr', 'coach', 'profile_updated_at',
    ];

    /** Domains where duplicate logical rows require separate correction. */
    private const COLLISION_KEYS = [
        'player_registrations' => ['registration_id'],
        'team_players' => ['team_id'],
        'team_payment_orders' => ['team_id', 'event_id'],
        'player_subscriptions' => ['subscription_id'],
        'positions' => ['category_event_id'],
        'ranking_scores' => ['ranking_list_id'],
        'ranking_score_legs' => ['category_event_id'],
        'rankings' => ['category_event'],
        'series_rankings' => ['series_id', 'category_id'],
        'leaderboards' => ['series_id', 'category_id', 'school_grade'],
        'event_nominations' => ['event_id', 'category_event_id'],
    ];

    private const OPPOSING_REFERENCE_PAIRS = [
        'team_fixture_players' => ['team1_id', 'team2_id'],
        'fixture_players' => ['team1_id', 'team2_id'],
        'team_fixture_results' => ['match_winner_id', 'match_loser_id'],
    ];

    public function candidates(
        int $perPage = 25,
        bool $includeReviewed = false,
        string $filter = 'all'
    ): LengthAwarePaginator
    {
        $query = $this->candidatePairQuery($includeReviewed);

        if ($filter === 'ranking_2026') {
            $rows = $query->orderBy('first_player_id')->orderBy('second_player_id')->get();
            $rankingKeys = $this->rankingDuplicatePairKeys($rows, 2026);
            $rows = $rows->filter(fn ($pair) => $rankingKeys->has(
                $this->candidatePairKey($pair->first_player_id, $pair->second_player_id)
            ))->values();
            $existingKeys = $rows->mapWithKeys(fn ($pair) => [
                $this->candidatePairKey($pair->first_player_id, $pair->second_player_id) => true,
            ]);
            foreach ($rankingKeys->keys()->diff($existingKeys->keys()) as $key) {
                [$firstId, $secondId] = array_map('intval', explode(':', $key));
                $rows->push((object) ['first_player_id' => $firstId, 'second_player_id' => $secondId]);
            }
            $hydratedRows = $this->hydrateCandidateRows($rows);
            $page = LengthAwarePaginator::resolveCurrentPage();
            $pageRows = $hydratedRows->slice(($page - 1) * $perPage, $perPage)->values();

            return new LengthAwarePaginator(
                $pageRows,
                $hydratedRows->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        if (in_array($filter, ['auto_resolvable', 'ranking_auto'], true)) {
            $scanLimit = 1000;
            $rows = $query->orderBy('first_player_id')->orderBy('second_player_id')
                ->limit($scanLimit + 1)->get();
            if ($rows->count() > $scanLimit) {
                throw ValidationException::withMessages([
                    'filter' => "More than {$scanLimit} duplicate pairs exist. Review or dismiss older candidates before running the automatic-resolution filter.",
                ]);
            }

            $rankingKeys = $this->rankingAutoResolvablePairKeys($rows);
            $registrationKeys = $this->registrationOverlapCandidatePairKeys($rows);
            $eligibleKeys = $rankingKeys->merge($registrationKeys);
            $rows = $rows->filter(fn ($pair) => $eligibleKeys->has(
                $this->candidatePairKey($pair->first_player_id, $pair->second_player_id)
            ))->values();
            $hydratedRows = $this->hydrateCandidateRows($rows, $rankingKeys)
                ->filter(function ($pair) use ($rankingKeys, $registrationKeys) {
                    /** @var Player $first */
                    $first = $pair->first['player'];
                    /** @var Player $second */
                    $second = $pair->second['player'];
                    $keep = $pair->recommended_keep_id === $first->id ? $first : $second;
                    $remove = $keep->is($first) ? $second : $first;
                    $key = $this->candidatePairKey($first->id, $second->id);
                    if ($rankingKeys->has($key)) {
                        return $this->recommendedCandidateIsMergeable($pair);
                    }

                    if (! $registrationKeys->has($key)) {
                        return false;
                    }

                    $resolutions = $this->autoResolvableRegistrationOverlaps($keep, $remove);

                    return $resolutions !== [] && $this->collisionBlockers($keep, $remove, $resolutions) === [];
                })
                ->values();
            $page = LengthAwarePaginator::resolveCurrentPage();
            $pageRows = $hydratedRows->slice(($page - 1) * $perPage, $perPage)->values();

            return new LengthAwarePaginator(
                $pageRows,
                $hydratedRows->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        $pairs = $query
            ->orderBy('first_player_id')->orderBy('second_player_id')
            ->paginate($perPage)->withQueryString();
        $pairs->setCollection($this->hydrateCandidateRows($pairs->getCollection()));

        return $pairs;
    }

    /** @return Collection<string, bool> */
    private function rankingDuplicatePairKeys(Collection $candidateRows, int $year): Collection
    {
        if ($candidateRows->isEmpty() || ! Schema::hasTable('series_rankings')) {
            return collect();
        }

        $playerIds = $candidateRows->flatMap(fn ($pair) => [
            (int) $pair->first_player_id, (int) $pair->second_player_id,
        ])->unique()->values();
        $rows = DB::table('series_rankings as sr')
            ->join('series as s', 's.id', '=', 'sr.series_id')
            ->where('s.year', $year)
            ->whereIn('sr.player_id', $playerIds)
            ->get(['sr.series_id', 'sr.category_id', 'sr.player_id']);

        $profiles = Player::query()->whereIn('id', $rows->pluck('player_id')->unique())
            ->get(['id', 'name', 'surname'])->keyBy('id');
        $normalize = fn ($value) => trim((string) preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(trim((string) $value))) ?? ''));
        $similarity = function (string $left, string $right): float {
            if ($left === $right) return 1.0;
            similar_text($left, $right, $percent);
            return $percent / 100;
        };
        $keys = collect();
        foreach ($rows->groupBy(fn ($row) => $this->seriesRankingCollisionKey($row->series_id, $row->category_id)) as $group) {
            $ids = $group->pluck('player_id')->map(fn ($id) => (int) $id)->unique()->values();
            foreach ($ids as $first) foreach ($ids as $second) {
                if ($first < $second && $profiles->has($first) && $profiles->has($second)) {
                    $firstName = $normalize($profiles[$first]->name);
                    $secondName = $normalize($profiles[$second]->name);
                    $firstSurname = $normalize($profiles[$first]->surname);
                    $secondSurname = $normalize($profiles[$second]->surname);
                    $nameScore = $similarity($firstName, $secondName);
                    $surnameScore = $similarity($firstSurname, $secondSurname);
                    $sameName = $firstName !== '' && $firstName === $secondName;
                    $sameSurname = $firstSurname !== '' && $firstSurname === $secondSurname;
                    $match = ($firstName !== '' && $firstName === $secondName && $firstSurname !== '' && $firstSurname === $secondSurname)
                        || ($nameScore >= .82 && $surnameScore >= .82)
                        || ($sameName && $surnameScore >= .65)
                        || ($sameSurname && $nameScore >= .65);
                    if (!$match) continue;
                    $keys->put($this->candidatePairKey($first, $second), true);
                }
            }
        }

        return $keys;
    }

    /**
     * Cheaply narrow the candidate set to likely paid-versus-abandoned pairs.
     * The full result/refund/fixture safety analysis still runs afterwards.
     */
    private function registrationOverlapCandidatePairKeys(Collection $candidateRows): Collection
    {
        foreach (['player_registrations', 'category_event_registrations', 'registration_order_items', 'registration_orders'] as $table) {
            if (! Schema::hasTable($table)) {
                return collect();
            }
        }

        if ($candidateRows->isEmpty()) {
            return collect();
        }

        $candidateKeys = $candidateRows->mapWithKeys(fn ($pair) => [
            $this->candidatePairKey($pair->first_player_id, $pair->second_player_id) => true,
        ]);
        $playerIds = $candidateRows->flatMap(fn ($pair) => [
            (int) $pair->first_player_id, (int) $pair->second_player_id,
        ])->unique()->values();

        $query = DB::table('player_registrations as pr1')
            ->join('category_event_registrations as cer1', 'cer1.registration_id', '=', 'pr1.registration_id')
            ->join('category_event_registrations as cer2', function ($join) {
                $join->on('cer2.category_event_id', '=', 'cer1.category_event_id')
                    ->whereColumn('cer2.registration_id', '<>', 'cer1.registration_id');
            })
            ->join('player_registrations as pr2', function ($join) {
                $join->on('pr2.registration_id', '=', 'cer2.registration_id')
                    ->whereColumn('pr1.player_id', '<', 'pr2.player_id');
            })
            ->join('registration_order_items as roi1', function ($join) {
                $join->on('roi1.registration_id', '=', 'cer1.registration_id')
                    ->on('roi1.category_event_id', '=', 'cer1.category_event_id');
            })
            ->join('registration_orders as ro1', 'ro1.id', '=', 'roi1.order_id')
            ->join('registration_order_items as roi2', function ($join) {
                $join->on('roi2.registration_id', '=', 'cer2.registration_id')
                    ->on('roi2.category_event_id', '=', 'cer2.category_event_id');
            })
            ->join('registration_orders as ro2', 'ro2.id', '=', 'roi2.order_id')
            ->whereIn('pr1.player_id', $playerIds)
            ->whereIn('pr2.player_id', $playerIds)
            ->whereNotNull('cer1.user_id')
            ->whereColumn('cer1.user_id', 'cer2.user_id')
            ->where(function ($directions) {
                $directions->where(function ($direction) {
                    $this->whereRegistrationEntryAuthoritative($direction, 'cer1', 'ro1');
                    $this->whereRegistrationEntryLooksAbandoned($direction, 'cer2', 'ro2');
                })->orWhere(function ($direction) {
                    $this->whereRegistrationEntryAuthoritative($direction, 'cer2', 'ro2');
                    $this->whereRegistrationEntryLooksAbandoned($direction, 'cer1', 'ro1');
                });
            })
            ->distinct()
            ->get(['pr1.player_id as first_player_id', 'pr2.player_id as second_player_id']);

        return $query->mapWithKeys(function ($pair) use ($candidateKeys) {
            $key = $this->candidatePairKey($pair->first_player_id, $pair->second_player_id);

            return $candidateKeys->has($key) ? [$key => true] : [];
        });
    }

    private function whereRegistrationEntryAuthoritative($query, string $entry, string $order): void
    {
        $query->where(function ($paid) use ($entry, $order) {
            $paid->where("{$entry}.payment_status_id", 1)
                ->orWhereNotNull("{$entry}.pf_transaction_id")
                ->orWhereNotNull("{$entry}.wallet_transaction_id")
                ->orWhere("{$order}.pay_status", true)
                ->orWhere("{$order}.payfast_paid", true)
                ->orWhere("{$order}.wallet_debited", true)
                ->orWhere("{$order}.wallet_reserved", '>', 0)
                ->orWhereNotNull("{$order}.payfast_pf_payment_id")
                ->orWhereNotNull("{$order}.wallet_transaction_id");
        });
    }

    private function whereRegistrationEntryLooksAbandoned($query, string $entry, string $order): void
    {
        $query->where(function ($unpaid) use ($entry) {
            $unpaid->whereNull("{$entry}.payment_status_id")->orWhere("{$entry}.payment_status_id", '<>', 1);
        })->whereNull("{$entry}.pf_transaction_id")
            ->whereNull("{$entry}.wallet_transaction_id")
            ->where(function ($refund) use ($entry) {
                $refund->whereNull("{$entry}.refund_status")->orWhereIn("{$entry}.refund_status", ['', 'not_refunded']);
            })
            ->where(function ($refund) use ($entry) {
                $refund->whereNull("{$entry}.refund_gross")->orWhere("{$entry}.refund_gross", 0);
            })
            ->where(function ($refund) use ($entry) {
                $refund->whereNull("{$entry}.refund_fee")->orWhere("{$entry}.refund_fee", 0);
            })
            ->where(function ($refund) use ($entry) {
                $refund->whereNull("{$entry}.refund_net")->orWhere("{$entry}.refund_net", 0);
            })
            ->whereNull("{$entry}.refunded_at")
            ->where("{$order}.pay_status", false)
            ->where("{$order}.payfast_paid", false)
            ->where("{$order}.wallet_debited", false)
            ->where("{$order}.wallet_reserved", 0)
            ->whereNull("{$order}.payfast_pf_payment_id")
            ->whereNull("{$order}.wallet_transaction_id");
    }

    /** @return array<int, array{first_id:int, second_id:int}> */
    public function allQuickCandidatePairs(int $limit = 400): array
    {
        $rows = $this->candidatePairQuery(false)
            ->orderBy('first_player_id')->orderBy('second_player_id')
            ->limit($limit + 1)->get();
        if ($rows->count() > $limit) {
            throw ValidationException::withMessages([
                'pairs' => "More than {$limit} duplicate candidates exist. Narrow the queue before starting a protected bulk merge.",
            ]);
        }

        return $this->hydrateCandidateRows($rows)
            ->filter(fn ($pair) => $pair->quick_merge !== null)
            ->map(fn ($pair) => [
                'first_id' => (int) $pair->first['player']->id,
                'second_id' => (int) $pair->second['player']->id,
            ])->values()->all();
    }

    private function candidatePairQuery(bool $includeReviewed)
    {
        if (Schema::hasColumn('players', 'identity_name_hash')) {
            $queries = collect(['identity_name_hash', 'identity_email_dob_hash', 'identity_cell_dob_hash'])
                ->map(fn ($column) => $this->indexedCandidateQuery($column, $includeReviewed));
            $query = $queries->shift();
            foreach ($queries as $candidateQuery) {
                $query->union($candidateQuery);
            }
        } else {
            // Transitional fallback while the scoped migration is not yet deployed.
            $query = DB::table('players as p1')
                ->join('players as p2', 'p1.id', '<', 'p2.id')
                ->select(['p1.id as first_player_id', 'p2.id as second_player_id'])
                ->where(function ($query) {
                $query->where(function ($sameName) {
                    $sameName
                        ->whereRaw('LOWER(TRIM(p1.name)) = LOWER(TRIM(p2.name))')
                        ->whereRaw('LOWER(TRIM(p1.surname)) = LOWER(TRIM(p2.surname))')
                        ->whereRaw("TRIM(COALESCE(p1.name, '')) <> ''")
                        ->whereRaw("TRIM(COALESCE(p1.surname, '')) <> ''");
                })->orWhere(function ($sameContactAndDob) {
                    $sameContactAndDob
                        ->whereNotNull('p1.dateOfBirth')
                        ->whereColumn('p1.dateOfBirth', 'p2.dateOfBirth')
                        ->where(function ($contact) {
                            $contact->where(function ($email) {
                                $email
                                    ->whereRaw("TRIM(COALESCE(p1.email, '')) <> ''")
                                    ->whereRaw('LOWER(TRIM(p1.email)) = LOWER(TRIM(p2.email))');
                            })->orWhere(function ($cell) {
                                $cell
                                    ->whereRaw("TRIM(COALESCE(p1.cellNr, '')) <> ''")
                                    ->whereRaw("REPLACE(REPLACE(TRIM(p1.cellNr), ' ', ''), '-', '') = REPLACE(REPLACE(TRIM(p2.cellNr), ' ', ''), '-', '')");
                            });
                        });
                });
            });
            $this->excludeReviewed($query, $includeReviewed);
        }

        return $query;
    }

    private function hydrateCandidateRows(Collection $rows, ?Collection $rankingAutoKeys = null): Collection
    {
        $playerIds = $rows
            ->flatMap(fn ($pair) => [$pair->first_player_id, $pair->second_player_id])
            ->unique()->values();
        $players = Player::with([
            'user:id,name,email,userName,userSurname,cell_nr',
            'users:id,name,email,userName,userSurname,cell_nr',
        ])
            ->whereIn('id', $playerIds)->get()->keyBy('id');
        $usageByPlayer = $this->usageMany($playerIds->all());
        $rankingAutoKeys ??= $this->rankingAutoResolvablePairKeys($rows);

        return $rows->filter(fn ($pair) => $players->has($pair->first_player_id) && $players->has($pair->second_player_id))
            ->map(function ($pair) use ($players, $usageByPlayer, $rankingAutoKeys) {
            $first = $players->get($pair->first_player_id);
            $second = $players->get($pair->second_player_id);

            $firstDescription = $this->describe($first, $usageByPlayer[$first->id] ?? []);
            $secondDescription = $this->describe($second, $usageByPlayer[$second->id] ?? []);
            $confidence = $this->confidence($first, $second);
            $quickMerge = $this->quickMergeRecommendation($firstDescription, $secondDescription, $confidence);

            return (object) [
                'first' => $firstDescription,
                'second' => $secondDescription,
                'confidence' => $confidence,
                'recommended_keep_id' => $this->recommendedFromDescriptions($firstDescription, $secondDescription),
                'quick_merge' => $quickMerge,
                'ranking_auto_merge' => $rankingAutoKeys->has($this->candidatePairKey($first->id, $second->id)),
                'decision' => Schema::hasTable('player_duplicate_decisions')
                    ? PlayerDuplicateDecision::query()
                        ->where('first_player_id', $first->id)
                        ->where('second_player_id', $second->id)->first()
                    : null,
            ];
        })->sortByDesc(fn ($pair) => $pair->quick_merge !== null)->values();
    }

    /** @return Collection<string, bool> */
    private function rankingAutoResolvablePairKeys(Collection $candidateRows): Collection
    {
        if ($candidateRows->isEmpty() || ! Schema::hasTable('series_rankings')) {
            return collect();
        }

        $requiredColumns = ['player_id', 'series_id', 'category_id', 'status'];
        if (collect($requiredColumns)->contains(fn (string $column) => ! Schema::hasColumn('series_rankings', $column))) {
            return collect();
        }

        $playerIds = $candidateRows->flatMap(fn ($pair) => [
            (int) $pair->first_player_id,
            (int) $pair->second_player_id,
        ])->unique()->values();
        $rankingRowsByPlayer = DB::table('series_rankings')
            ->whereIn('player_id', $playerIds)
            ->get($requiredColumns)
            ->groupBy(fn ($row) => (int) $row->player_id);

        $pairCollisions = $candidateRows->mapWithKeys(function ($pair) use ($rankingRowsByPlayer) {
            $firstId = (int) $pair->first_player_id;
            $secondId = (int) $pair->second_player_id;
            $rows = collect($rankingRowsByPlayer->get($firstId, collect()))
                ->merge($rankingRowsByPlayer->get($secondId, collect()));
            $collisions = $rows
                ->groupBy(fn ($row) => $this->seriesRankingCollisionKey($row->series_id, $row->category_id))
                ->filter(function (Collection $logicalRows) use ($firstId, $secondId) {
                    $logicalPlayerIds = $logicalRows->pluck('player_id')->map(fn ($id) => (int) $id)->unique();

                    return $logicalPlayerIds->contains($firstId)
                        && $logicalPlayerIds->contains($secondId)
                        && $logicalRows->every(fn ($row) => $row->status === 'calculated');
                });

            return [$this->candidatePairKey($firstId, $secondId) => $collisions->pluck('series_id')
                ->map(fn ($id) => (int) $id)->unique()->values()];
        })->filter(fn (Collection $seriesIds) => $seriesIds->isNotEmpty());

        if ($pairCollisions->isEmpty()) {
            return collect();
        }

        $reviewedSeriesIds = DB::table('series_rankings')
            ->whereIn('series_id', $pairCollisions->flatten()->unique())
            ->where('status', 'reviewed')
            ->pluck('series_id')->map(fn ($id) => (int) $id)->unique();

        return $pairCollisions
            ->filter(fn (Collection $seriesIds) => $seriesIds->diff($reviewedSeriesIds)->isNotEmpty())
            ->map(fn () => true);
    }

    private function candidatePairKey(mixed $firstId, mixed $secondId): string
    {
        return min((int) $firstId, (int) $secondId).':'.max((int) $firstId, (int) $secondId);
    }

    private function recommendedCandidateIsMergeable(object $pair): bool
    {
        /** @var Player $first */
        $first = $pair->first['player'];
        /** @var Player $second */
        $second = $pair->second['player'];
        $keep = $pair->recommended_keep_id === $first->id ? $first : $second;
        $remove = $keep->is($first) ? $second : $first;

        if (filled($keep->dateOfBirth) && filled($remove->dateOfBirth)
            && substr((string) $keep->dateOfBirth, 0, 10) !== substr((string) $remove->dateOfBirth, 0, 10)) {
            return false;
        }

        return $this->collisionBlockers($keep, $remove) === [];
    }

    private function indexedCandidateQuery(string $hashColumn, bool $includeReviewed)
    {
        $query = DB::table('players as p1')
            ->join('players as p2', function ($join) use ($hashColumn) {
                $join->on("p1.{$hashColumn}", '=', "p2.{$hashColumn}")
                    ->on('p1.id', '<', 'p2.id');
            })
            ->whereNotNull("p1.{$hashColumn}")
            ->select(['p1.id as first_player_id', 'p2.id as second_player_id']);

        $this->excludeReviewed($query, $includeReviewed);

        return $query;
    }

    private function excludeReviewed($query, bool $includeReviewed): void
    {
        if ($includeReviewed || ! Schema::hasTable('player_duplicate_decisions')) {
            return;
        }

        $query->whereNotExists(function ($decision) {
            $decision->selectRaw('1')->from('player_duplicate_decisions as pdd')
                ->whereColumn('pdd.first_player_id', 'p1.id')
                ->whereColumn('pdd.second_player_id', 'p2.id');
        });
    }

    public function describe(Player $player, ?array $knownUsage = null): array
    {
        $usage = $knownUsage ?? $this->usage($player->id);
        $owners = $player->users->push($player->user)->filter()->unique('id')
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])->values();

        return [
            'player' => $player,
            'owners' => $owners,
            'emails' => $owners->pluck('email')->push($player->email)->filter()->unique()->values(),
            'usage' => $usage,
            'usage_total' => array_sum($usage),
            'is_empty' => array_sum($usage) === 0,
        ];
    }

    public function usage(int $playerId): array
    {
        return $this->usageMany([$playerId])[$playerId] ?? [];
    }

    /** @return array<int, array<string, int>> */
    public function usageMany(array $playerIds): array
    {
        $playerIds = collect($playerIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $usage = collect($playerIds)->mapWithKeys(fn ($id) => [$id => []])->all();
        if ($playerIds === []) {
            return $usage;
        }

        foreach ($this->availableReferences() as $table => $columns) {
            foreach ($columns as $column) {
                $counts = DB::table($table)->select($column)->selectRaw('COUNT(*) as aggregate')
                    ->whereIn($column, $playerIds)->groupBy($column)->get();
                foreach ($counts as $row) {
                    $playerId = (int) $row->{$column};
                    $usage[$playerId][$table] = ($usage[$playerId][$table] ?? 0) + (int) $row->aggregate;
                }
            }
        }

        return $usage;
    }

    public function recommendedKeep(Player $first, Player $second): Player
    {
        $usage = $this->usageMany([$first->id, $second->id]);
        $first->loadMissing(['user:id,name,email', 'users:id,name,email']);
        $second->loadMissing(['user:id,name,email', 'users:id,name,email']);
        $recommendedId = $this->recommendedFromDescriptions(
            $this->describe($first, $usage[$first->id] ?? []),
            $this->describe($second, $usage[$second->id] ?? []),
        );

        return $recommendedId === $second->id ? $second : $first;
    }

    public function quickMergeAnalysis(Player $first, Player $second): array
    {
        $this->assertCandidatePair($first, $second);
        $usage = $this->usageMany([$first->id, $second->id]);
        $first->loadMissing(['user:id,name,email', 'users:id,name,email']);
        $second->loadMissing(['user:id,name,email', 'users:id,name,email']);
        $firstDescription = $this->describe($first, $usage[$first->id] ?? []);
        $secondDescription = $this->describe($second, $usage[$second->id] ?? []);
        $recommendation = $this->quickMergeRecommendation(
            $firstDescription,
            $secondDescription,
            $this->confidence($first, $second)
        );

        if ($recommendation === null) {
            throw ValidationException::withMessages([
                'quick_merge' => 'Quick merge is available only for a strong identity match where exactly one profile has linked history. Use the full comparison instead.',
            ]);
        }

        $keep = $recommendation['keep_id'] === $first->id ? $first : $second;
        $remove = $keep->is($first) ? $second : $first;

        return $this->analyze($keep, $remove);
    }

    /**
     * Classify every selected pair so a blocked candidate does not hide the safe candidates.
     *
     * @param  array<int, array{first_id:int, second_id:int}>  $pairs
     */
    public function quickMergeBatchReview(array $pairs): array
    {
        if ($pairs === []) {
            throw ValidationException::withMessages(['pairs' => 'Select at least one quick-merge candidate.']);
        }

        $playerIds = collect($pairs)->flatMap(fn (array $pair) => [
            (int) $pair['first_id'], (int) $pair['second_id'],
        ])->filter()->unique()->values();
        $players = Player::with(['user:id,name,email', 'users:id,name,email'])
            ->whereIn('id', $playerIds)->get()->keyBy('id');
        $usage = $this->usageMany($playerIds->all());
        $registrationHistory = $this->registrationHistoryImpactMany($playerIds->all());

        $items = collect($pairs)->map(function (array $pair) use ($players, $usage, $registrationHistory) {
            $firstId = (int) $pair['first_id'];
            $secondId = (int) $pair['second_id'];
            $first = $players->get($firstId);
            $second = $players->get($secondId);
            if (! $first || ! $second) {
                return [
                    'pair' => compact('firstId', 'secondId'),
                    'analysis' => null,
                    'reasons' => ['One or both selected player profiles no longer exist.'],
                ];
            }

            try {
                $this->assertCandidatePair($first, $second);
                $firstDescription = $this->describe($first, $usage[$firstId] ?? []);
                $secondDescription = $this->describe($second, $usage[$secondId] ?? []);
                $confidence = $this->confidence($first, $second);
                $recommendation = $this->quickMergeRecommendation($firstDescription, $secondDescription, $confidence);
                if ($recommendation === null) {
                    throw ValidationException::withMessages([
                        'quick_merge' => 'Quick merge is available only for a strong identity match where exactly one profile has linked history. Use the full comparison instead.',
                    ]);
                }
                $keepDescription = $recommendation['keep_id'] === $firstId ? $firstDescription : $secondDescription;
                $removeDescription = $recommendation['remove_id'] === $firstId ? $firstDescription : $secondDescription;
                $analysis = $this->fastQuickAnalysis(
                    $keepDescription,
                    $removeDescription,
                    $confidence,
                    $registrationHistory[$recommendation['keep_id']] ?? [],
                );
            } catch (ValidationException $exception) {
                $analysis = null;
                $reasons = collect($exception->errors())->flatten();
                try {
                    $firstDescription ??= $this->describe($first, $usage[$firstId] ?? []);
                    $secondDescription ??= $this->describe($second, $usage[$secondId] ?? []);
                    $recommendedId = $this->recommendedFromDescriptions($firstDescription, $secondDescription);
                    $keep = $recommendedId === $firstId ? $first : $second;
                    $remove = $keep->is($first) ? $second : $first;
                    $analysis = $this->analyze($keep, $remove);
                    $reasons = $reasons->merge(collect($analysis['blockers'])->pluck('message'));
                } catch (ValidationException $analysisException) {
                    $reasons = $reasons->merge(collect($analysisException->errors())->flatten());
                }

                return [
                    'pair' => compact('firstId', 'secondId'),
                    'analysis' => $analysis,
                    'reasons' => $reasons->unique()->values()->all(),
                ];
            }

            return [
                'pair' => compact('firstId', 'secondId'),
                'analysis' => $analysis,
                'reasons' => collect($analysis['blockers'])->pluck('message')->unique()->values()->all(),
            ];
        })->values();

        $eligibleIndexes = fn () => $items->keys()->filter(
            fn (int $index) => $items[$index]['analysis'] !== null && $items[$index]['reasons'] === []
        );

        $eligibleIndexes()->groupBy(fn (int $index) => $items[$index]['analysis']['remove']->id)
            ->filter(fn (Collection $indexes) => $indexes->count() > 1)
            ->each(function (Collection $indexes) use ($items) {
                $resolution = $this->resolveOverlappingQuickKeeper(
                    $indexes->map(fn (int $index) => $items[$index]['analysis'])->values()
                );
                foreach ($indexes as $index) {
                    $item = $items->get($index);
                    if ($resolution !== null && $item['analysis']['keep']->id === $resolution['keep_id']) {
                        $item['analysis']['overlap_resolution'] = $resolution['reason'];
                    } else {
                        $item['reasons'][] = $resolution === null
                            ? 'This empty profile matches more than one retained player and its first name does not identify one safe destination. Review this cluster manually.'
                            : "Automatically routed this empty profile to retained profile #{$resolution['keep_id']}; this alternative pair was excluded.";
                    }
                    $items->put($index, $item);
                }
            });

        $active = $eligibleIndexes();
        $removeIds = $active->map(fn (int $index) => $items[$index]['analysis']['remove']->id);
        $mixedIds = $active->map(fn (int $index) => $items[$index]['analysis']['keep']->id)
            ->intersect($removeIds)->unique();
        foreach ($active as $index) {
            $analysis = $items[$index]['analysis'];
            if ($mixedIds->contains($analysis['keep']->id) || $mixedIds->contains($analysis['remove']->id)) {
                $item = $items->get($index);
                $item['reasons'][] = 'A profile cannot be both retained and removed in the same batch.';
                $items->put($index, $item);
            }
        }

        $eligibleIndexes()->groupBy(fn (int $index) => $items[$index]['analysis']['keep']->id)
            ->each(function (Collection $indexes) use ($items) {
                try {
                    $this->assertCompatibleSharedKeeperValues(
                        $indexes->map(fn (int $index) => $items[$index]['analysis'])
                    );
                } catch (ValidationException $exception) {
                    $reasons = collect($exception->errors())->flatten()->unique()->values()->all();
                    foreach ($indexes as $index) {
                        $item = $items->get($index);
                        $item['reasons'] = array_values(array_unique([...$item['reasons'], ...$reasons]));
                        $items->put($index, $item);
                    }
                }
            });

        $readyAnalyses = $eligibleIndexes()->map(fn (int $index) => $items[$index]['analysis'])->values();
        $batch = $this->batchPayload($readyAnalyses);
        $batch['mode'] = 'quick';
        $batch['selected_count'] = $items->count();
        $batch['skipped'] = $items->filter(fn (array $item) => $item['reasons'] !== [])
            ->map(function (array $item) {
                $analysis = $item['analysis'];

                return [
                    'first_id' => $item['pair']['firstId'],
                    'second_id' => $item['pair']['secondId'],
                    'keep' => $analysis['keep'] ?? null,
                    'remove' => $analysis['remove'] ?? null,
                    'reasons' => $item['reasons'],
                    'contexts' => $analysis
                        ? collect($analysis['blockers'])->flatMap(fn (array $blocker) => $blocker['contexts'] ?? [])->values()->all()
                        : [],
                ];
            })->values()->all();

        return $batch;
    }

    /**
     * Choose one keeper only when the empty profile has meaningful first-name evidence.
     * Shared family contact details and dates of birth are never sufficient on their own.
     *
     * @return array{keep_id:int, reason:string}|null
     */
    private function resolveOverlappingQuickKeeper(Collection $analyses): ?array
    {
        if ($analyses->count() < 2) {
            return null;
        }

        $ranked = $analyses->values()->map(function (array $analysis, int $position) {
            $keep = $analysis['keep'];
            $remove = $analysis['remove'];
            $sourceFirst = $this->comparableName((string) $remove->name);
            $keepFirst = $this->comparableName((string) $keep->name);
            $sourceSurname = $this->comparableName((string) $remove->surname);
            $keepSurname = $this->comparableName((string) $keep->surname);

            $firstScore = $this->namePartScore($sourceFirst, $keepFirst, true);
            if ($firstScore === 0) {
                return null;
            }
            $score = $firstScore + $this->namePartScore($sourceSurname, $keepSurname, false);
            if (filled($remove->gender) && filled($keep->gender)) {
                $score += (int) $remove->gender === (int) $keep->gender ? 15 : -60;
            }
            if ((int) $remove->userId > 0 && (int) $remove->userId === (int) $keep->userId) {
                $score += 10;
            }

            return [
                'analysis' => $analysis,
                'score' => $score,
                'usage' => (int) ($analysis['impact']['keep']['usage_total'] ?? 0),
                'identity_key' => $this->identityNameKey($keep),
                'position' => $position,
            ];
        })->filter()->sort(function (array $left, array $right) {
            // Preserve the submitted pair order when evidence is tied. This
            // keeps the review result stable and aligned with the admin's
            // explicit selection instead of making the database ID decisive.
            return [$right['score'], $right['usage'], -$right['position']]
                <=> [$left['score'], $left['usage'], -$left['position']];
        })->values();

        if ($ranked->isEmpty()) {
            return null;
        }
        $winner = $ranked->first();
        $runnerUp = $ranked->get(1);
        if ($runnerUp && $winner['score'] === $runnerUp['score']
            && $winner['identity_key'] !== $runnerUp['identity_key']) {
            return null;
        }

        $keepId = (int) $winner['analysis']['keep']->id;

        return [
            'keep_id' => $keepId,
            'reason' => "Overlap resolved from the empty profile's first name, gender and identity details; retain #{$keepId}.",
        ];
    }

    private function namePartScore(string $source, string $candidate, bool $required): int
    {
        if ($source === '' || $candidate === '') {
            return 0;
        }
        if ($source === $candidate) {
            return $required ? 100 : 35;
        }
        if (str_starts_with($source, $candidate.' ') || str_starts_with($candidate, $source.' ')) {
            return $required ? 85 : 25;
        }
        if (min(strlen($source), strlen($candidate)) >= 4 && levenshtein($source, $candidate) <= 1) {
            return $required ? 75 : 15;
        }

        return 0;
    }

    private function comparableName(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($value))));
    }

    /**
     * @param  array<int, array{first_id:int, second_id:int}>  $pairs
     * @return array{analyses:array<int, array>, digest:string, confirmation_phrase:string}
     */
    public function quickMergeBatchAnalysis(array $pairs): array
    {
        $batch = $this->quickMergeBatchReview($pairs);
        if ($batch['skipped'] !== []) {
            throw ValidationException::withMessages([
                'pairs' => collect($batch['skipped'])->map(function (array $item) {
                    return "Profiles #{$item['first_id']} and #{$item['second_id']}: ".implode(' ', $item['reasons']);
                })->implode(' '),
            ]);
        }

        return collect($batch)->only(['analyses', 'digest', 'confirmation_phrase'])->all();
    }

    /** @param array<int, array{first_id:int, second_id:int}> $pairs */
    public function plannedMergeBatchReview(array $pairs): array
    {
        if ($pairs === []) {
            throw ValidationException::withMessages(['pairs' => 'Select at least one suggested merge.']);
        }

        $items = collect($pairs)->map(function (array $pair) {
            $first = Player::find((int) $pair['first_id']);
            $second = Player::find((int) $pair['second_id']);
            if (! $first || ! $second) {
                return ['pair' => $pair, 'analysis' => null, 'reasons' => ['One or both profiles no longer exist.']];
            }

            try {
                $keep = $this->recommendedKeep($first, $second);
                $remove = $keep->is($first) ? $second : $first;
                $analysis = $this->analyze($keep, $remove);

                return [
                    'pair' => $pair,
                    'analysis' => $analysis,
                    'reasons' => collect($analysis['blockers'])->pluck('message')->unique()->values()->all(),
                ];
            } catch (ValidationException $exception) {
                return [
                    'pair' => $pair,
                    'analysis' => null,
                    'reasons' => collect($exception->errors())->flatten()->unique()->values()->all(),
                ];
            }
        })->values();

        $eligible = fn () => $items->keys()->filter(
            fn (int $index) => $items[$index]['analysis'] !== null && $items[$index]['reasons'] === []
        );
        $eligible()->groupBy(fn (int $index) => $items[$index]['analysis']['remove']->id)
            ->filter(fn (Collection $indexes) => $indexes->count() > 1)
            ->each(fn (Collection $indexes) => $this->rejectPlannedIndexes(
                $items, $indexes, 'The same source profile appears in more than one selected plan.'
            ));
        $eligible()->groupBy(fn (int $index) => $items[$index]['analysis']['keep']->id)
            ->filter(fn (Collection $indexes) => $indexes->count() > 1)
            ->each(fn (Collection $indexes) => $this->rejectPlannedIndexes(
                $items, $indexes, 'Multiple history-bearing profiles target the same canonical profile. Merge this cluster one step at a time.'
            ));
        $active = $eligible();
        $removeIds = $active->map(fn (int $index) => $items[$index]['analysis']['remove']->id);
        $mixedIds = $active->map(fn (int $index) => $items[$index]['analysis']['keep']->id)
            ->intersect($removeIds)->unique();
        foreach ($active as $index) {
            $analysis = $items[$index]['analysis'];
            if ($mixedIds->contains($analysis['keep']->id) || $mixedIds->contains($analysis['remove']->id)) {
                $this->rejectPlannedIndexes(
                    $items, collect([$index]), 'A profile cannot be retained in one selected plan and removed in another.'
                );
            }
        }

        $batch = $this->batchPayload($eligible()->map(fn (int $index) => $items[$index]['analysis'])->values());
        $batch['mode'] = 'planned';
        $batch['selected_count'] = $items->count();
        $batch['skipped'] = $items->filter(fn (array $item) => $item['reasons'] !== [])
            ->map(fn (array $item) => [
                'first_id' => (int) $item['pair']['first_id'],
                'second_id' => (int) $item['pair']['second_id'],
                'keep' => $item['analysis']['keep'] ?? null,
                'remove' => $item['analysis']['remove'] ?? null,
                'reasons' => $item['reasons'],
                'contexts' => $item['analysis']
                    ? collect($item['analysis']['blockers'])->flatMap(fn (array $blocker) => $blocker['contexts'] ?? [])->values()->all()
                    : [],
            ])->values()->all();

        return $batch;
    }

    private function rejectPlannedIndexes(Collection $items, Collection $indexes, string $reason): void
    {
        foreach ($indexes as $index) {
            $item = $items->get($index);
            $item['reasons'][] = $reason;
            $items->put($index, $item);
        }
    }

    /** @param array<int, array{first_id:int, second_id:int}> $pairs */
    public function plannedMergeBatchAnalysis(array $pairs): array
    {
        $batch = $this->plannedMergeBatchReview($pairs);
        if ($batch['skipped'] !== []) {
            throw ValidationException::withMessages(['pairs' => 'One or more suggested plans are no longer safe. Review the refreshed selection.']);
        }

        return collect($batch)->only(['analyses', 'digest', 'confirmation_phrase'])->all();
    }

    /** @param array<int, array{first_id:int, second_id:int}> $pairs */
    public function mergePlannedBatch(array $pairs, User $approvedBy, string $expectedDigest, string $reason): int
    {
        $this->assertTransactionalStorage([
            'players', 'user_players', 'player_merge_audits', 'player_duplicate_decisions', 'activity_log',
            'category_event_registrations',
            'ranking_lists', 'ranking_list_category_events', 'ranking_audit_logs', 'audit_events',
            ...array_keys($this->availableReferences()),
        ]);

        return DB::transaction(function () use ($pairs, $approvedBy, $expectedDigest, $reason) {
            $playerIds = collect($pairs)->flatMap(fn (array $pair) => [$pair['first_id'], $pair['second_id']])
                ->unique()->sort()->values();
            Player::whereIn('id', $playerIds)->orderBy('id')->lockForUpdate()->get();
            $batch = $this->plannedMergeBatchAnalysis($pairs);
            if (! hash_equals($batch['digest'], $expectedDigest)) {
                throw ValidationException::withMessages([
                    'confirmation' => 'One or more profiles or linked records changed after review. Nothing was merged.',
                ]);
            }

            foreach ($batch['analyses'] as $analysis) {
                $fieldSources = collect($analysis['fields'])
                    ->mapWithKeys(fn (array $field, string $name) => [$name => $field['recommended']])->all();
                $this->executeAnalyzedMerge(
                    $analysis['keep'], $analysis['remove'], $approvedBy, $fieldSources, $analysis, $reason
                );
            }

            return count($batch['analyses']);
        }, 3);
    }

    /**
     * @param  array<int, array{first_id:int, second_id:int}>  $pairs
     */
    public function mergeQuickBatch(
        array $pairs,
        User $approvedBy,
        string $expectedDigest,
        string $reason
    ): int {
        $this->assertTransactionalStorage([
            'players', 'user_players', 'player_merge_audits',
            'player_duplicate_decisions', 'activity_log',
        ]);

        return DB::transaction(function () use ($pairs, $approvedBy, $expectedDigest, $reason) {
            $playerIds = collect($pairs)->flatMap(fn (array $pair) => [
                (int) $pair['first_id'], (int) $pair['second_id'],
            ])->unique()->sort()->values();
            $lockedPlayers = Player::query()->whereIn('id', $playerIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $batch = $this->quickMergeBatchAnalysis($pairs);
            if (! hash_equals($batch['digest'], $expectedDigest)) {
                throw ValidationException::withMessages([
                    'confirmation' => 'One or more profiles or linked records changed after the batch review opened. No profiles were merged; review the refreshed batch before confirming again.',
                ]);
            }

            $prepared = [];
            foreach ($batch['analyses'] as $analysis) {
                /** @var Player $keep */
                $keep = $lockedPlayers->get($analysis['keep']->id);
                /** @var Player $remove */
                $remove = $lockedPlayers->get($analysis['remove']->id);
                if (! $keep || ! $remove) {
                    throw ValidationException::withMessages([
                        'pairs' => 'One or more reviewed profiles no longer exist. No profiles were merged.',
                    ]);
                }

                $fieldSources = collect($analysis['fields'])
                    ->mapWithKeys(fn (array $field, string $name) => [$name => $field['recommended']])->all();
                $keptBefore = $this->playerSnapshot($keep);
                $removedSnapshot = $this->playerSnapshot($remove);
                $sourceUserIds = $this->sourceUserIds($remove);

                foreach ($sourceUserIds as $userId) {
                    if (! DB::table('user_players')->where(['user_id' => $userId, 'player_id' => $keep->id])->exists()) {
                        DB::table('user_players')->insert([
                            'user_id' => $userId,
                            'player_id' => $keep->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                $removedOwnerLinks = DB::table('user_players')->where('player_id', $remove->id)->count();
                DB::table('user_players')->where('player_id', $remove->id)->delete();

                foreach (self::PROFILE_FIELDS as $field) {
                    if (($fieldSources[$field] ?? 'keep') === 'remove') {
                        $keep->{$field} = $remove->{$field};
                    }
                }
                if (! $keep->userId && $remove->userId) {
                    $keep->userId = $remove->userId;
                }
                $keep->profile_complete = $keep->isProfileComplete();
                $keep->save();

                $prepared[] = compact(
                    'analysis', 'keep', 'remove', 'fieldSources', 'keptBefore',
                    'removedSnapshot', 'sourceUserIds', 'removedOwnerLinks'
                );
            }

            $remainingUsage = $this->usageMany(
                collect($prepared)->pluck('remove.id')->map(fn ($id) => (int) $id)->all()
            );
            foreach ($prepared as $item) {
                $removeId = $item['remove']->id;
                if (array_sum($remainingUsage[$removeId] ?? []) > 0
                    || DB::table('user_players')->where('player_id', $removeId)->exists()) {
                    throw ValidationException::withMessages([
                        'pairs' => "Source profile #{$removeId} gained linked records during confirmation. No profiles were merged.",
                    ]);
                }
            }

            foreach ($prepared as $item) {
                /** @var Player $keep */
                $keep = $item['keep'];
                /** @var Player $remove */
                $remove = $item['remove'];
                $manifest = [
                    'user_players' => [
                        'transferred_user_ids' => $item['sourceUserIds']->values()->all(),
                        'removed_source_links' => $item['removedOwnerLinks'],
                    ],
                ];

                $remove->delete();
                PlayerMergeAudit::create([
                    'kept_player_id' => $keep->id,
                    'removed_player_id' => $item['removedSnapshot']['id'],
                    'approved_by' => $approvedBy->id,
                    'reason' => $reason,
                    'status' => 'completed',
                    'kept_before_snapshot' => $item['keptBefore'],
                    'removed_snapshot' => $item['removedSnapshot'],
                    'field_resolutions' => $item['fieldSources'],
                    'impact_snapshot' => $this->serializableImpact($item['analysis']['impact']),
                    'change_manifest' => $manifest,
                    'merged_at' => now(),
                ]);

                PlayerDuplicateDecision::query()
                    ->where('first_player_id', $remove->id)->orWhere('second_player_id', $remove->id)->delete();

                activity('player-profile-merge')->causedBy($approvedBy)->performedOn($keep)
                    ->withProperties([
                        'kept_player_id' => $keep->id,
                        'removed_player' => $item['removedSnapshot'],
                        'transferred_user_ids' => $item['sourceUserIds']->values()->all(),
                        'reason' => $reason,
                        'change_manifest' => $manifest,
                    ])->log('Duplicate player profile merged');
            }

            return count($batch['analyses']);
        }, 3);
    }

    public function analyze(Player $keep, Player $remove, bool $allowPublishedRankingCollisions = false): array
    {
        $this->assertCandidatePair($keep, $remove);
        $keep->loadMissing(['user:id,name,email', 'users:id,name,email']);
        $remove->loadMissing(['user:id,name,email', 'users:id,name,email']);

        $registrationOverlapResolutions = $this->autoResolvableRegistrationOverlaps($keep, $remove);
        $blockers = $this->collisionBlockers($keep, $remove, $registrationOverlapResolutions, $allowPublishedRankingCollisions);
        if (filled($keep->dateOfBirth) && filled($remove->dateOfBirth)
            && substr((string) $keep->dateOfBirth, 0, 10) !== substr((string) $remove->dateOfBirth, 0, 10)) {
            array_unshift($blockers, [
                'domain' => 'identity',
                'message' => 'The profiles have different recorded dates of birth. Mark them as not duplicates or correct the source data before merging.',
            ]);
        }

        $fieldComparison = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $keepValue = $this->fieldValue($keep, $field);
            $removeValue = $this->fieldValue($remove, $field);
            $fieldComparison[$field] = [
                'keep' => $keepValue,
                'remove' => $removeValue,
                'different' => $keepValue !== $removeValue,
                'recommended' => blank($keepValue) && filled($removeValue) ? 'remove' : 'keep',
            ];
        }

        $referenceState = $this->referenceState($keep->id, $remove->id);
        $registrationHistory = $this->registrationHistoryState($keep->id, $remove->id);
        $rankingRebuildSeriesIds = $this->autoResolvableSeriesRankingSeriesIds($keep, $remove);
        if ($allowPublishedRankingCollisions) {
            $rankingRebuildSeriesIds = collect($rankingRebuildSeriesIds)
                ->merge($this->publishedCollisionSeriesIds($keep, $remove))
                ->unique()->values()->all();
        }
        $impact = [
            'keep' => $this->describe($keep),
            'remove' => $this->describe($remove),
            'references' => $referenceState['impact'],
            'registration_history' => $registrationHistory['impact'],
            'owners_to_transfer' => $this->sourceUserIds($remove)->diff($this->sourceUserIds($keep))->values()->all(),
            'ranking_rebuild_series_ids' => $rankingRebuildSeriesIds,
            'registration_overlap_resolutions' => $registrationOverlapResolutions,
        ];
        $digestPayload = [
            'keep' => $this->stablePlayerSnapshot($keep),
            'remove' => $this->stablePlayerSnapshot($remove),
            'references' => $referenceState['fingerprints'],
            'registration_history' => $registrationHistory['fingerprints'],
            'blockers' => $blockers,
            'ranking_rebuild_series_ids' => $rankingRebuildSeriesIds,
            'registration_overlap_resolutions' => $registrationOverlapResolutions,
        ];

        return [
            'keep' => $keep,
            'remove' => $remove,
            'confidence' => $this->confidence($keep, $remove),
            'fields' => $fieldComparison,
            'impact' => $impact,
            'blockers' => $blockers,
            'can_merge' => $blockers === [],
            'digest' => hash('sha256', json_encode($digestPayload, JSON_THROW_ON_ERROR)),
            'confirmation_phrase' => 'MERGE',
        ];
    }

    public function merge(
        Player $keep,
        Player $remove,
        User $approvedBy,
        array $fieldSources = [],
        ?string $expectedDigest = null,
        string $reason = 'Approved duplicate player merge',
        bool $allowPublishedRankingCollisions = false
    ): Player {
        if ($keep->is($remove)) {
            throw ValidationException::withMessages(['remove_player_id' => 'Choose two different profiles.']);
        }
        $this->assertTransactionalStorage([
            'players', 'user_players', 'player_merge_audits',
            'player_duplicate_decisions', 'activity_log',
            'category_event_registrations',
            'ranking_lists', 'ranking_list_category_events', 'ranking_audit_logs', 'audit_events',
            ...array_keys($this->availableReferences()),
        ]);

        return DB::transaction(function () use ($keep, $remove, $approvedBy, $fieldSources, $expectedDigest, $reason, $allowPublishedRankingCollisions) {
            $keep = Player::query()->lockForUpdate()->findOrFail($keep->id);
            $remove = Player::query()->lockForUpdate()->findOrFail($remove->id);
            $analysis = $this->analyze($keep, $remove, $allowPublishedRankingCollisions);

            if ($expectedDigest !== null && ! hash_equals($analysis['digest'], $expectedDigest)) {
                throw ValidationException::withMessages([
                    'confirmation' => 'The profiles or their linked records changed after the review was opened. Review the refreshed impact before confirming again.',
                ]);
            }
            if (! $analysis['can_merge']) {
                throw ValidationException::withMessages([
                    'remove_player_id' => collect($analysis['blockers'])->pluck('message')->implode(' '),
                ]);
            }

            return $this->executeAnalyzedMerge($keep, $remove, $approvedBy, $fieldSources, $analysis, $reason, $allowPublishedRankingCollisions);
        }, 3);
    }

    /**
     * Merge profiles whose only blockers are published/archived ranking
     * collisions. Published rows are archived and preserved; affected series
     * are rebuilt as calculated rows for explicit review before publication.
     */
    public function mergePublished(
        Player $keep,
        Player $remove,
        User $approvedBy,
        ?string $expectedDigest,
        string $reason
    ): Player {
        return $this->merge(
            $keep,
            $remove,
            $approvedBy,
            [],
            $expectedDigest,
            $reason,
            true
        );
    }

    private function executeAnalyzedMerge(
        Player $keep,
        Player $remove,
        User $approvedBy,
        array $fieldSources,
        array $analysis,
        string $reason,
        bool $allowPublishedRankingCollisions = false
    ): Player {
        $keptBefore = $this->playerSnapshot($keep);
        $removedSnapshot = $this->playerSnapshot($remove);
        $manifest = [];
        $sourceUserIds = $this->sourceUserIds($remove);
        $rankingRebuildSeriesIds = collect($analysis['impact']['ranking_rebuild_series_ids'] ?? [])
            ->map(fn ($seriesId) => (int) $seriesId)->filter()->unique()->values();

        foreach ($analysis['impact']['registration_overlap_resolutions'] ?? [] as $resolution) {
            if (($resolution['action'] ?? null) !== 'withdraw_unpaid_duplicate') {
                continue;
            }

            $entry = CategoryEventRegistration::query()->findOrFail($resolution['duplicate_entry_id']);
            app(EntryService::class)->retireUnpaidDuplicateForPlayerMerge(
                $entry,
                $approvedBy,
                (int) $resolution['canonical_registration_id'],
                'Unpaid duplicate registration retired during player merge #'.$remove->id.' into #'.$keep->id.'. '.$reason
            );
            $manifest['registration_overlap_resolutions'][] = $resolution;
        }

        if ($rankingRebuildSeriesIds->isNotEmpty()) {
            if ($allowPublishedRankingCollisions) {
                $archived = DB::table('series_rankings')
                    ->whereIn('series_id', $rankingRebuildSeriesIds)
                    ->where('status', 'published')
                    ->update(['status' => 'archived']);
                $manifest['series_rankings']['archived_published_rows'] = $archived;
            }
            $calculatedRankingRows = DB::table('series_rankings')
                ->whereIn('series_id', $rankingRebuildSeriesIds)
                ->whereIn('player_id', [$keep->id, $remove->id])
                ->where('status', 'calculated')
                ->get();

            DB::table('series_rankings')
                ->whereIn('id', $calculatedRankingRows->pluck('id'))
                ->delete();

            $manifest['series_rankings']['auto_resolved_calculated_rows'] = [
                'series_ids' => $rankingRebuildSeriesIds->all(),
                'deleted_rows' => $calculatedRankingRows->map(fn ($row) => (array) $row)->all(),
            ];
        }

        foreach ($sourceUserIds as $userId) {
            if (! DB::table('user_players')->where(['user_id' => $userId, 'player_id' => $keep->id])->exists()) {
                DB::table('user_players')->insert([
                    'user_id' => $userId, 'player_id' => $keep->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $removedOwnerLinks = DB::table('user_players')->where('player_id', $remove->id)->count();
        DB::table('user_players')->where('player_id', $remove->id)->delete();
        $manifest['user_players'] = [
            'transferred_user_ids' => $sourceUserIds->values()->all(),
            'removed_source_links' => $removedOwnerLinks,
        ];

        foreach ($this->availableReferences() as $table => $columns) {
            foreach ($columns as $column) {
                $query = DB::table($table)->where($column, $remove->id);
                if ($allowPublishedRankingCollisions && $table === 'series_rankings') {
                    $query->where(function ($rankingRows) {
                        $rankingRows->whereNull('status')->orWhere('status', '!=', 'archived');
                    });
                }
                $rowIds = Schema::hasColumn($table, 'id')
                    ? (clone $query)->pluck('id')->map(fn ($id) => (int) $id)->all()
                    : [];
                $count = $query->update([$column => $keep->id]);
                if ($count > 0) {
                    $manifest[$table][$column] = ['count' => $count, 'row_ids' => $rowIds];
                }
            }
        }

        foreach (self::PROFILE_FIELDS as $field) {
            $source = $fieldSources[$field] ?? $analysis['fields'][$field]['recommended'];
            if ($source === 'remove') {
                $keep->{$field} = $remove->{$field};
            }
        }
        if (! $keep->userId && $remove->userId) {
            $keep->userId = $remove->userId;
        }
        $keep->profile_complete = $keep->isProfileComplete();
        $keep->save();

        foreach ($rankingRebuildSeriesIds as $seriesId) {
            $report = app(RankingRebuildService::class)->rebuild(
                Series::query()->findOrFail($seriesId),
                ['replaceCalculatedOnly' => true]
            );
            if (! ($report['persisted'] ?? false)) {
                throw ValidationException::withMessages([
                    'series_rankings' => 'The calculated ranking for series #'.$seriesId.' could not be rebuilt safely. Nothing was merged.',
                ]);
            }

            $manifest['series_rankings']['rebuilds'][] = [
                'series_id' => $seriesId,
                'run_id' => $report['run_id'],
                'total_rows' => $report['total_rows'],
                'warnings' => $report['warnings'],
                'topology' => $report['topology'],
                'status' => 'calculated',
            ];
        }

        $remaining = $this->usage($remove->id);
        if ($allowPublishedRankingCollisions && isset($remaining['series_rankings'])) {
            $remaining['series_rankings'] = DB::table('series_rankings')
                ->where('player_id', $remove->id)
                ->where(function ($rankingRows) {
                    $rankingRows->whereNull('status')->orWhere('status', '!=', 'archived');
                })->count();
        }
        if (array_sum($remaining) > 0 || DB::table('user_players')->where('player_id', $remove->id)->exists()) {
            throw ValidationException::withMessages([
                'remove_player_id' => 'The source profile still has linked records. Nothing was deleted; the reference registry must be updated before retrying.',
            ]);
        }

        $remove->delete();
        PlayerMergeAudit::create([
            'kept_player_id' => $keep->id,
            'removed_player_id' => $removedSnapshot['id'],
            'approved_by' => $approvedBy->id,
            'reason' => $reason,
            'status' => 'completed',
            'kept_before_snapshot' => $keptBefore,
            'removed_snapshot' => $removedSnapshot,
            'field_resolutions' => collect(self::PROFILE_FIELDS)
                ->mapWithKeys(fn ($field) => [$field => $fieldSources[$field] ?? $analysis['fields'][$field]['recommended']])->all(),
            'impact_snapshot' => $this->serializableImpact($analysis['impact']),
            'change_manifest' => $manifest,
            'merged_at' => now(),
        ]);

        PlayerDuplicateDecision::query()
            ->where('first_player_id', $remove->id)->orWhere('second_player_id', $remove->id)->delete();

        activity('player-profile-merge')->causedBy($approvedBy)->performedOn($keep)
            ->withProperties([
                'kept_player_id' => $keep->id,
                'removed_player' => $removedSnapshot,
                'transferred_user_ids' => $sourceUserIds->values()->all(),
                'reason' => $reason,
                'change_manifest' => $manifest,
            ])->log('Duplicate player profile merged');

        return $keep->refresh();
    }

    /** @param array<int, string> $tables */
    private function assertTransactionalStorage(array $tables): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $engines = DB::table('information_schema.TABLES')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->whereIn('TABLE_NAME', array_values(array_unique($tables)))
            ->pluck('ENGINE', 'TABLE_NAME');
        $unsafe = $engines->filter(fn ($engine) => strtoupper((string) $engine) !== 'INNODB')->keys()->values();
        if ($unsafe->isNotEmpty()) {
            throw ValidationException::withMessages([
                'storage' => 'Player merging is paused until transaction-safe storage is installed. Unsafe tables: '.$unsafe->implode(', ').'.',
            ]);
        }
    }

    private function batchPayload(Collection $analyses): array
    {
        $digestPairs = $analyses->map(fn (array $analysis) => [
            'keep_id' => $analysis['keep']->id,
            'remove_id' => $analysis['remove']->id,
            'digest' => $analysis['digest'],
        ])->sortBy([
            ['keep_id', 'asc'],
            ['remove_id', 'asc'],
        ])->values()->all();
        $count = $analyses->count();

        return [
            'analyses' => $analyses->all(),
            'digest' => hash('sha256', json_encode($digestPairs, JSON_THROW_ON_ERROR)),
            'confirmation_phrase' => $count > 0 ? 'MERGE' : null,
        ];
    }

    private function fastQuickAnalysis(
        array $keepDescription,
        array $removeDescription,
        array $confidence,
        array $keepRegistrationHistory
    ): array {
        /** @var Player $keep */
        $keep = $keepDescription['player'];
        /** @var Player $remove */
        $remove = $removeDescription['player'];
        $fields = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $keepValue = $this->fieldValue($keep, $field);
            $removeValue = $this->fieldValue($remove, $field);
            $fields[$field] = [
                'keep' => $keepValue,
                'remove' => $removeValue,
                'different' => $keepValue !== $removeValue,
                'recommended' => blank($keepValue) && filled($removeValue) ? 'remove' : 'keep',
            ];
        }

        $references = [];
        foreach (['keep' => $keepDescription, 'remove' => $removeDescription] as $side => $description) {
            foreach ($description['usage'] as $table => $count) {
                $references[$table]['player_reference'][$side] = (int) $count;
            }
        }
        $registrationHistory = [];
        foreach ($keepRegistrationHistory as $table => $columns) {
            foreach ($columns as $column => $count) {
                $registrationHistory[$table][$column] = ['keep' => (int) $count, 'remove' => 0];
            }
        }
        $keepOwnerIds = collect($keepDescription['owners'])->pluck('id');
        $removeOwnerIds = collect($removeDescription['owners'])->pluck('id');
        $impact = [
            'keep' => $keepDescription,
            'remove' => $removeDescription,
            'references' => $references,
            'registration_history' => $registrationHistory,
            'owners_to_transfer' => $removeOwnerIds->diff($keepOwnerIds)->values()->all(),
        ];
        $digest = hash('sha256', json_encode([
            'keep' => $this->playerSnapshot($keep),
            'remove' => $this->playerSnapshot($remove),
            'remove_usage' => $removeDescription['usage'],
            'remove_owner_ids' => $removeOwnerIds->sort()->values()->all(),
        ], JSON_THROW_ON_ERROR));

        return [
            'keep' => $keep,
            'remove' => $remove,
            'confidence' => $confidence,
            'fields' => $fields,
            'impact' => $impact,
            'blockers' => [],
            'can_merge' => true,
            'digest' => $digest,
            'confirmation_phrase' => 'MERGE',
        ];
    }

    /** @return array<int, array<string, array<string, int>>> */
    private function registrationHistoryImpactMany(array $playerIds): array
    {
        if ($playerIds === [] || ! Schema::hasTable('player_registrations')) {
            return [];
        }
        $registrationOwners = DB::table('player_registrations')
            ->whereIn('player_id', $playerIds)
            ->get(['player_id', 'registration_id'])
            ->groupBy(fn ($row) => (int) $row->registration_id)
            ->map(fn (Collection $rows) => $rows->pluck('player_id')->map(fn ($id) => (int) $id)->unique()->values());
        $registrationIds = $registrationOwners->keys()->all();
        if ($registrationIds === []) {
            return [];
        }

        $impact = [];
        foreach (self::REGISTRATION_HISTORY_REFERENCES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $counts = DB::table($table)->select($column)->selectRaw('COUNT(*) as aggregate')
                    ->whereIn($column, $registrationIds)->groupBy($column)->get();
                foreach ($counts as $row) {
                    foreach ($registrationOwners->get((int) $row->{$column}, collect()) as $playerId) {
                        $impact[$playerId][$table][$column] = ($impact[$playerId][$table][$column] ?? 0)
                            + (int) $row->aggregate;
                    }
                }
            }
        }

        return $impact;
    }

    private function assertCompatibleSharedKeeperValues(Collection $analyses): void
    {
        foreach ($analyses->groupBy(fn (array $analysis) => $analysis['keep']->id) as $keeperAnalyses) {
            if ($keeperAnalyses->count() < 2) {
                continue;
            }

            foreach (self::PROFILE_FIELDS as $field) {
                $suggestedValues = $keeperAnalyses
                    ->filter(fn (array $analysis) => $analysis['fields'][$field]['recommended'] === 'remove')
                    ->map(fn (array $analysis) => $analysis['fields'][$field]['remove'])
                    ->filter(fn ($value) => filled($value))->uniqueStrict();
                if ($suggestedValues->count() > 1) {
                    throw ValidationException::withMessages([
                        'pairs' => "Selected empty profiles suggest conflicting {$field} values for retained profile #{$keeperAnalyses->first()['keep']->id}. Review those candidates separately.",
                    ]);
                }
            }
        }
    }

    public function recordDecision(Player $first, Player $second, User $decidedBy, string $decision, string $reason): void
    {
        $this->assertCandidatePair($first, $second);
        [$firstId, $secondId] = collect([$first->id, $second->id])->sort()->values()->all();
        PlayerDuplicateDecision::updateOrCreate(
            ['first_player_id' => $firstId, 'second_player_id' => $secondId],
            ['decision' => $decision, 'reason' => $reason, 'decided_by' => $decidedBy->id]
        );

        activity('player-duplicate-review')->causedBy($decidedBy)
            ->withProperties(compact('firstId', 'secondId', 'decision', 'reason'))
            ->log('Duplicate player candidate reviewed');
    }

    /** Resolve an old player ID through one or more completed merges. */
    public function canonicalPlayerId(int $playerId): int
    {
        $visited = [];
        while (! in_array($playerId, $visited, true)) {
            $visited[] = $playerId;
            $next = PlayerMergeAudit::query()->where('removed_player_id', $playerId)->value('kept_player_id');
            if (! $next) {
                return $playerId;
            }
            $playerId = (int) $next;
        }

        return $playerId;
    }

    private function confidence(Player $first, Player $second): array
    {
        $sameName = $this->identityNameKey($first) === $this->identityNameKey($second);
        $firstDob = substr((string) $first->dateOfBirth, 0, 10);
        $secondDob = substr((string) $second->dateOfBirth, 0, 10);
        $sameDob = filled($firstDob) && $firstDob === $secondDob;
        $differentDob = filled($firstDob) && filled($secondDob) && $firstDob !== $secondDob;
        $sameEmail = $this->normalizedContact($first->email) !== ''
            && $this->normalizedContact($first->email) === $this->normalizedContact($second->email);
        $sameCell = $this->normalizedCell($first->cellNr) !== ''
            && $this->normalizedCell($first->cellNr) === $this->normalizedCell($second->cellNr);

        if ($differentDob) {
            return ['level' => 'blocked', 'label' => 'Conflicting identity', 'class' => 'danger', 'reasons' => ['Dates of birth differ']];
        }
        if ($sameName && $sameDob) {
            return ['level' => 'high', 'label' => 'High confidence', 'class' => 'success', 'reasons' => ['Same normalized name and date of birth']];
        }
        if ($sameDob && ($sameEmail || $sameCell)) {
            return ['level' => 'medium', 'label' => 'Strong supporting match', 'class' => 'info', 'reasons' => ['Same date of birth and contact detail']];
        }

        return ['level' => 'review', 'label' => 'Manual review', 'class' => 'warning', 'reasons' => ['Name matches but identity data is incomplete']];
    }

    private function recommendedFromDescriptions(array $first, array $second): int
    {
        $score = function (array $description): int {
            $player = $description['player'];
            $completeFields = collect(self::PROFILE_FIELDS)->filter(fn ($field) => filled($player->{$field}))->count();

            return ($description['usage_total'] * 100)
                + ($description['owners']->count() * 20)
                + ($player->profile_complete ? 10 : 0)
                + $completeFields;
        };

        $firstScore = $score($first);
        $secondScore = $score($second);
        if ($firstScore === $secondScore) {
            return min($first['player']->id, $second['player']->id);
        }

        return $firstScore > $secondScore ? $first['player']->id : $second['player']->id;
    }

    private function quickMergeRecommendation(array $first, array $second, array $confidence): ?array
    {
        if (! in_array($confidence['level'], ['high', 'medium'], true) || $first['is_empty'] === $second['is_empty']) {
            return null;
        }

        $keep = $first['is_empty'] ? $second : $first;
        $remove = $first['is_empty'] ? $first : $second;

        return [
            'keep_id' => $keep['player']->id,
            'remove_id' => $remove['player']->id,
            'history_count' => $keep['usage_total'],
        ];
    }

    private function assertCandidatePair(Player $first, Player $second): void
    {
        if ($first->is($second)) {
            throw ValidationException::withMessages(['remove_player_id' => 'Choose two different profiles.']);
        }
        $confidence = $this->confidence($first, $second);
        $sameName = $this->identityNameKey($first) === $this->identityNameKey($second);
        if (! $sameName && ! in_array($confidence['level'], ['high', 'medium'], true)) {
            throw ValidationException::withMessages(['remove_player_id' => 'These profiles do not meet the duplicate-candidate rules.']);
        }
    }

    private function availableReferences(): array
    {
        if ($this->resolvedReferences !== null) {
            return $this->resolvedReferences;
        }

        $available = [];
        foreach (self::PLAYER_REFERENCES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $present = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if ($present !== []) {
                $available[$table] = $present;
            }
        }

        return $this->resolvedReferences = $available;
    }

    /**
     * Resolve the narrow safe case where two profiles entered the same category,
     * but one entry is an abandoned unpaid order and the other is authoritative.
     * No financial or result row is deleted; the abandoned entry is only retired.
     *
     * @return array<int, array<string, mixed>>
     */
    private function autoResolvableRegistrationOverlaps(Player $keep, Player $remove): array
    {
        foreach (['player_registrations', 'category_event_registrations', 'registration_order_items', 'registration_orders'] as $table) {
            if (! Schema::hasTable($table)) {
                return [];
            }
        }

        $rows = DB::table('category_event_registrations as cer')
            ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
            ->whereIn('pr.player_id', [$keep->id, $remove->id])
            ->get([
                'cer.id as entry_id', 'cer.category_event_id', 'cer.registration_id', 'cer.user_id',
                'cer.status', 'cer.payment_status_id', 'cer.pf_transaction_id',
                'cer.wallet_transaction_id', 'cer.refund_status', 'cer.refund_gross',
                'cer.refund_fee', 'cer.refund_net', 'cer.refunded_at', 'pr.player_id',
            ])
            ->groupBy('category_event_id')
            ->filter(fn (Collection $entries) => $entries->pluck('player_id')->map(fn ($id) => (int) $id)->unique()->count() === 2);

        $resolutions = [];
        foreach ($rows as $categoryEventId => $entries) {
            if ($entries->count() !== 2
                || $entries->pluck('user_id')->filter()->unique()->count() !== 1
                || $entries->contains(fn ($entry) => $entry->user_id === null)) {
                continue;
            }

            $evaluated = $entries->map(fn ($entry) => $this->registrationOverlapEvidence($entry));
            $canonical = $evaluated->first(fn (array $entry) => $entry['authoritative']);
            $duplicate = $evaluated->first(fn (array $entry) => $entry['safe_unpaid_duplicate']);
            if (! $canonical || ! $duplicate || $canonical['entry_id'] === $duplicate['entry_id']
                || $evaluated->where('authoritative', true)->count() !== 1
                || $evaluated->where('safe_unpaid_duplicate', true)->count() !== 1) {
                continue;
            }

            $context = $this->categoryEventContext((int) $categoryEventId);
            $resolutions[] = [
                ...$context,
                'category_event_id' => (int) $categoryEventId,
                'canonical_entry_id' => $canonical['entry_id'],
                'canonical_registration_id' => $canonical['registration_id'],
                'canonical_player_id' => $canonical['player_id'],
                'canonical_status' => $canonical['status'],
                'canonical_evidence' => $canonical['evidence'],
                'duplicate_entry_id' => $duplicate['entry_id'],
                'duplicate_registration_id' => $duplicate['registration_id'],
                'duplicate_player_id' => $duplicate['player_id'],
                'duplicate_status' => $duplicate['status'],
                'duplicate_order_id' => $duplicate['order_id'],
                'action' => Str::startsWith((string) $duplicate['status'], 'withdrawn')
                    ? 'preserve_withdrawn'
                    : 'withdraw_unpaid_duplicate',
            ];
        }

        return $resolutions;
    }

    /** @return array<string, mixed> */
    private function registrationOverlapEvidence(object $entry): array
    {
        $orderIds = DB::table('registration_order_items')
            ->where('registration_id', $entry->registration_id)
            ->pluck('order_id')->filter()->unique();
        $orders = DB::table('registration_orders')->whereIn('id', $orderIds)->get();
        $hasPayfastTransaction = Schema::hasTable('transactions_pf')
            && Schema::hasColumn('transactions_pf', 'custom_int5')
            && $orderIds->isNotEmpty()
            && DB::table('transactions_pf')->whereIn('custom_int5', $orderIds)->exists();
        $hasPaidOrder = $orders->contains(fn ($order) => (bool) ($order->pay_status ?? false)
            || (bool) ($order->payfast_paid ?? false)
            || (bool) ($order->wallet_debited ?? false)
            || (float) ($order->wallet_reserved ?? 0) > 0
            || filled($order->payfast_pf_payment_id ?? null)
            || filled($order->wallet_transaction_id ?? null));
        $resultCount = Schema::hasTable('category_results')
            ? DB::table('category_results')->where('registration_id', $entry->registration_id)->count()
            : 0;
        $hasPayment = (int) $entry->payment_status_id === 1
            || filled($entry->pf_transaction_id)
            || filled($entry->wallet_transaction_id)
            || $hasPaidOrder
            || $hasPayfastTransaction;
        $hasRefund = ! in_array($entry->refund_status, [null, '', 'not_refunded'], true)
            || (float) ($entry->refund_gross ?? 0) !== 0.0
            || (float) ($entry->refund_fee ?? 0) !== 0.0
            || (float) ($entry->refund_net ?? 0) !== 0.0
            || filled($entry->refunded_at);
        $hasCompetitionHistory = $this->registrationHasCompetitionHistory((int) $entry->registration_id);
        $singleUnpaidOrder = $orders->count() === 1 && ! $hasPaidOrder && ! $hasPayfastTransaction;

        return [
            'entry_id' => (int) $entry->entry_id,
            'registration_id' => (int) $entry->registration_id,
            'player_id' => (int) $entry->player_id,
            'status' => (string) $entry->status,
            'order_id' => $orderIds->count() === 1 ? (int) $orderIds->first() : null,
            'authoritative' => $hasPayment || $resultCount > 0,
            'safe_unpaid_duplicate' => $singleUnpaidOrder
                && ! $hasPayment && ! $hasRefund && $resultCount === 0 && ! $hasCompetitionHistory,
            'evidence' => array_values(array_filter([
                $hasPayment ? 'verified payment' : null,
                $resultCount > 0 ? $resultCount.' saved result'.($resultCount === 1 ? '' : 's') : null,
            ])),
        ];
    }

    private function registrationHasCompetitionHistory(int $registrationId): bool
    {
        foreach ([
            'fixtures' => ['registration1_id', 'registration2_id', 'winner_registration'],
            'fixture_results' => ['winner_registration', 'loser_registration'],
            'practice_fixtures' => ['registration1_id', 'registration2_id'],
            'practice_results' => ['winner_registration', 'loser_registration'],
            'draw_group_registrations' => ['registration_id'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)
                    && DB::table($table)->where($column, $registrationId)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function categoryEventContext(int $categoryEventId): array
    {
        if (! Schema::hasTable('category_events')) {
            return [];
        }

        $row = DB::table('category_events as ce')
            ->leftJoin('events as e', 'e.id', '=', 'ce.event_id')
            ->leftJoin('categories as c', 'c.id', '=', 'ce.category_id')
            ->where('ce.id', $categoryEventId)
            ->first(['ce.event_id', 'ce.category_id', 'e.name as event_name', 'c.name as category_name']);

        return $row ? [
            'event_id' => $row->event_id === null ? null : (int) $row->event_id,
            'event_name' => $row->event_name,
            'category_id' => $row->category_id === null ? null : (int) $row->category_id,
            'category_name' => $row->category_name,
        ] : [];
    }

    /** @return array<int, array<string, mixed>> */
    private function registrationOverlapContexts(Player $keep, Player $remove, array $categoryEventIds): array
    {
        return collect($categoryEventIds)->map(function ($categoryEventId) use ($keep, $remove) {
            $entries = DB::table('category_event_registrations as cer')
                ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
                ->where('cer.category_event_id', $categoryEventId)
                ->whereIn('pr.player_id', [$keep->id, $remove->id])
                ->get(['cer.id as entry_id', 'cer.registration_id', 'cer.status', 'cer.payment_status_id', 'pr.player_id'])
                ->map(fn ($entry) => [
                    'entry_id' => (int) $entry->entry_id,
                    'registration_id' => (int) $entry->registration_id,
                    'player_id' => (int) $entry->player_id,
                    'status' => $entry->status,
                    'paid' => (int) $entry->payment_status_id === 1,
                ])->all();

            return [
                'type' => 'tournament_registration_overlap',
                'category_event_id' => (int) $categoryEventId,
                ...$this->categoryEventContext((int) $categoryEventId),
                'entries' => $entries,
            ];
        })->values()->all();
    }

    private function collisionBlockers(Player $keep, Player $remove, ?array $registrationOverlapResolutions = null, bool $allowPublishedRankingCollisions = false): array
    {
        $blockers = [];
        $autoResolvableRankingKeys = collect($this->autoResolvableSeriesRankingCollisions($keep, $remove))
            ->mapWithKeys(fn (array $collision) => [
                $this->seriesRankingCollisionKey($collision['series_id'], $collision['category_id']) => true,
            ]);

        foreach (self::COLLISION_KEYS as $table => $keyColumns) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'player_id')
                || collect($keyColumns)->contains(fn ($column) => ! Schema::hasColumn($table, $column))) {
                continue;
            }
            foreach (DB::table($table)->where('player_id', $remove->id)->get($keyColumns) as $sourceRow) {
                $match = DB::table($table)->where('player_id', $keep->id);
                foreach ($keyColumns as $column) {
                    $value = $sourceRow->{$column};
                    $value === null ? $match->whereNull($column) : $match->where($column, $value);
                }
                if ($match->exists()) {
                    if ($allowPublishedRankingCollisions && $table === 'series_rankings'
                        && $this->publishedCollisionSeriesIds($keep, $remove)->contains((int) $sourceRow->series_id)) {
                        continue;
                    }
                    if ($table === 'series_rankings' && $autoResolvableRankingKeys->has(
                        $this->seriesRankingCollisionKey($sourceRow->series_id, $sourceRow->category_id)
                    )) {
                        continue;
                    }

                    $blockers[] = [
                        'domain' => $table,
                        'message' => $table === 'series_rankings'
                            ? $this->seriesRankingCollisionMessage($keep, $remove, $sourceRow)
                            : 'Both profiles already have a '.str_replace('_', ' ', $table).' record for the same logical item. Resolve that collision before merging.',
                        'contexts' => $table === 'series_rankings'
                            ? [$this->seriesRankingCollisionContext($keep, $remove, $sourceRow)]
                            : [],
                    ];
                    break;
                }
            }
        }

        foreach (self::OPPOSING_REFERENCE_PAIRS as $table => [$firstColumn, $secondColumn]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $firstColumn) || ! Schema::hasColumn($table, $secondColumn)) {
                continue;
            }
            $opposingRows = DB::table($table)->where(function ($query) use ($firstColumn, $secondColumn, $keep, $remove) {
                $query->where(function ($side) use ($firstColumn, $secondColumn, $keep, $remove) {
                    $side->where($firstColumn, $keep->id)->where($secondColumn, $remove->id);
                })->orWhere(function ($side) use ($firstColumn, $secondColumn, $keep, $remove) {
                    $side->where($firstColumn, $remove->id)->where($secondColumn, $keep->id);
                });
            })->get();
            if ($opposingRows->isNotEmpty()) {
                $contexts = $this->opposingReferenceContexts($table, $opposingRows);
                $blockers[] = [
                    'domain' => $table,
                    'message' => 'The profiles appear on opposing sides of the same '.str_replace('_', ' ', $table).' record. Investigate it before merging.',
                    'contexts' => $contexts,
                ];
            }
        }

        if (Schema::hasTable('player_registrations') && Schema::hasTable('category_event_registrations')) {
            $registrationOverlapResolutions ??= $this->autoResolvableRegistrationOverlaps($keep, $remove);
            $resolvedCategoryEventIds = collect($registrationOverlapResolutions)
                ->pluck('category_event_id')->map(fn ($id) => (int) $id)->unique();
            $keepRegistrationIds = DB::table('player_registrations')->where('player_id', $keep->id)->pluck('registration_id');
            $removeRegistrationIds = DB::table('player_registrations')->where('player_id', $remove->id)->pluck('registration_id');
            if ($keepRegistrationIds->isNotEmpty() && $removeRegistrationIds->isNotEmpty()) {
                $keepCategoryEvents = DB::table('category_event_registrations')
                    ->whereIn('registration_id', $keepRegistrationIds)->pluck('category_event_id')->unique();
                $overlapCategoryEventIds = DB::table('category_event_registrations')
                    ->whereIn('registration_id', $removeRegistrationIds)
                    ->whereIn('category_event_id', $keepCategoryEvents)
                    ->pluck('category_event_id')->map(fn ($id) => (int) $id)->unique()
                    ->diff($resolvedCategoryEventIds)->values();
                if ($overlapCategoryEventIds->isNotEmpty()) {
                    $blockers[] = [
                        'domain' => 'tournament_registration_overlap',
                        'message' => 'Both profiles have registrations in the same tournament category. Resolve which entry and result are valid before merging so a ranking calculation cannot count the player twice.',
                        'contexts' => $this->registrationOverlapContexts(
                            $keep,
                            $remove,
                            $overlapCategoryEventIds->all()
                        ),
                    ];
                }
            }
        }

        if (Schema::hasTable('player_registrations') && Schema::hasTable('category_results')) {
            $keepResults = DB::table('category_results as cr')
                ->join('player_registrations as pr', 'pr.registration_id', '=', 'cr.registration_id')
                ->where('pr.player_id', $keep->id)
                ->get(['cr.event_id', 'cr.category_id']);
            foreach ($keepResults as $result) {
                if (DB::table('category_results as cr')
                    ->join('player_registrations as pr', 'pr.registration_id', '=', 'cr.registration_id')
                    ->where('pr.player_id', $remove->id)
                    ->where('cr.event_id', $result->event_id)
                    ->where('cr.category_id', $result->category_id)
                    ->exists()) {
                    $blockers[] = [
                        'domain' => 'tournament_result_overlap',
                        'message' => 'Both profiles have a saved result in the same tournament category. Resolve the duplicate result before merging so rankings remain unambiguous.',
                    ];
                    break;
                }
            }
        }

        return collect($blockers)->unique(fn ($blocker) => $blocker['domain'].'|'.$blocker['message'])->values()->all();
    }

    /** @return Collection<int, int> */
    private function publishedCollisionSeriesIds(Player $keep, Player $remove): Collection
    {
        if (! Schema::hasTable('series_rankings')) {
            return collect();
        }

        return DB::table('series_rankings')
            ->join('series', 'series.id', '=', 'series_rankings.series_id')
            ->whereIn('player_id', [$keep->id, $remove->id])
            ->where('series.year', 2026)
            ->whereIn('status', ['published', 'archived'])
            ->get(['series_id', 'category_id', 'player_id', 'status'])
            ->groupBy(fn ($row) => $this->seriesRankingCollisionKey($row->series_id, $row->category_id))
            ->filter(function (Collection $rows) use ($keep, $remove): bool {
                $ids = $rows->pluck('player_id')->map(fn ($id) => (int) $id)->unique();

                return $ids->contains($keep->id) && $ids->contains($remove->id)
                    && $rows->every(fn ($row) => in_array($row->status, ['published', 'archived'], true));
            })
            ->map(fn (Collection $rows) => (int) $rows->first()->series_id)
            ->unique()->values();
    }

    /** @return array<int, array{series_id:int, category_id:int|null}> */
    private function autoResolvableSeriesRankingCollisions(Player $keep, Player $remove): array
    {
        $requiredColumns = ['player_id', 'series_id', 'category_id', 'status'];
        if (! Schema::hasTable('series_rankings')
            || collect($requiredColumns)->contains(fn (string $column) => ! Schema::hasColumn('series_rankings', $column))) {
            return [];
        }

        $rows = DB::table('series_rankings')
            ->whereIn('player_id', [$keep->id, $remove->id])
            ->get($requiredColumns);

        $collisions = $rows
            ->groupBy(fn ($row) => $this->seriesRankingCollisionKey($row->series_id, $row->category_id))
            ->filter(function (Collection $logicalRows) use ($keep, $remove) {
                $playerIds = $logicalRows->pluck('player_id')->map(fn ($id) => (int) $id)->unique();

                return $playerIds->contains($keep->id)
                    && $playerIds->contains($remove->id)
                    && $logicalRows->every(fn ($row) => $row->status === 'calculated');
            })
            ->map(fn (Collection $logicalRows) => [
                'series_id' => (int) $logicalRows->first()->series_id,
                'category_id' => $logicalRows->first()->category_id === null
                    ? null
                    : (int) $logicalRows->first()->category_id,
            ])
            ->values();

        if ($collisions->isEmpty()) {
            return [];
        }

        // An in-progress reviewed run must be published or deliberately
        // reopened before a merge creates a replacement calculated snapshot.
        // Published and archived snapshots are preserved by the merge rebuild.
        $reviewedSeriesIds = DB::table('series_rankings')
            ->whereIn('series_id', $collisions->pluck('series_id')->unique())
            ->where('status', 'reviewed')
            ->pluck('series_id')->map(fn ($id) => (int) $id)->unique();

        return $collisions
            ->reject(fn (array $collision) => $reviewedSeriesIds->contains($collision['series_id']))
            ->all();
    }

    /** @return array<int, int> */
    private function autoResolvableSeriesRankingSeriesIds(Player $keep, Player $remove): array
    {
        return collect($this->autoResolvableSeriesRankingCollisions($keep, $remove))
            ->pluck('series_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function seriesRankingCollisionKey(mixed $seriesId, mixed $categoryId): string
    {
        return (int) $seriesId.'|'.($categoryId === null ? 'null' : (int) $categoryId);
    }

    private function seriesRankingCollisionMessage(Player $keep, Player $remove, object $sourceRow): string
    {
        $context = $this->seriesRankingCollisionContext($keep, $remove, $sourceRow);
        $statusSummary = collect($context['rows'])
            ->map(fn (array $row) => '#'.$row['player_id'].' '.($row['status'] ?? 'blank'))
            ->unique()->implode(', ');

        if (($context['series_status_counts']['reviewed'] ?? 0) > 0
            && collect($context['rows'])->every(fn (array $row) => $row['status'] === 'calculated')) {
            return 'Series #'.$context['series_id'].' has an in-progress reviewed ranking run. Complete or reopen that ranking review before merging; the colliding profile rows are '.$statusSummary.'.';
        }

        return 'Series #'.$context['series_id'].' / category #'.($context['category_id'] ?? 'none')
            .' has a non-calculated collision ('.$statusSummary.'). Reviewed, published or archived profile collisions require ranking review first.';
    }

    /** @return array<string, mixed> */
    private function seriesRankingCollisionContext(Player $keep, Player $remove, object $sourceRow): array
    {
        $rows = DB::table('series_rankings')
            ->where('series_id', $sourceRow->series_id)
            ->whereIn('player_id', [$keep->id, $remove->id])
            ->when(
                $sourceRow->category_id === null,
                fn ($query) => $query->whereNull('category_id'),
                fn ($query) => $query->where('category_id', $sourceRow->category_id)
            )
            ->get(['id', 'player_id', 'status', 'run_id'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'player_id' => (int) $row->player_id,
                'status' => $row->status,
                'run_id' => $row->run_id,
            ])->all();

        $seriesStatusCounts = DB::table('series_rankings')
            ->where('series_id', $sourceRow->series_id)
            ->selectRaw("COALESCE(status, '<blank>') as ranking_status, COUNT(*) as row_count")
            ->groupBy('status')
            ->pluck('row_count', 'ranking_status')
            ->map(fn ($count) => (int) $count)->all();

        return [
            'type' => 'series_ranking',
            'series_id' => (int) $sourceRow->series_id,
            'category_id' => $sourceRow->category_id === null ? null : (int) $sourceRow->category_id,
            'rows' => $rows,
            'series_status_counts' => $seriesStatusCounts,
        ];
    }

    private function opposingReferenceContexts(string $table, Collection $rows): array
    {
        return $rows->map(function ($row) use ($table) {
            $fixtureTable = match ($table) {
                'team_fixture_players', 'team_fixture_results' => 'team_fixtures',
                'fixture_players' => 'fixtures',
                default => null,
            };
            $fixtureId = match ($table) {
                'team_fixture_players', 'team_fixture_results' => $row->team_fixture_id ?? null,
                'fixture_players' => $row->fixture_id ?? null,
                default => null,
            };
            $fixture = $fixtureTable && $fixtureId
                ? DB::table($fixtureTable)->where('id', $fixtureId)->first()
                : null;
            $draw = $fixture?->draw_id ? DB::table('draws')->where('id', $fixture->draw_id)->first() : null;
            $eventId = $draw?->event_id;
            if (! $eventId && $draw?->category_event_id) {
                $eventId = DB::table('category_events')->where('id', $draw->category_event_id)->value('event_id');
            }
            $event = $eventId ? DB::table('events')->where('id', $eventId)->first() : null;
            $resultTable = $fixtureTable === 'team_fixtures' ? 'team_fixture_results' : 'fixture_results';
            $resultForeignKey = $fixtureTable === 'team_fixtures' ? 'team_fixture_id' : 'fixture_id';

            return [
                'record_id' => (int) $row->id,
                'fixture_id' => $fixtureId ? (int) $fixtureId : null,
                'fixture_created_at' => $fixture?->created_at,
                'draw_id' => $draw?->id ? (int) $draw->id : null,
                'draw_name' => $draw?->drawName,
                'event_id' => $event?->id ? (int) $event->id : null,
                'event_name' => $event?->name,
                'event_start_date' => $event?->start_date,
                'event_end_date' => $event?->end_date,
                'result_count' => $fixtureId && Schema::hasTable($resultTable)
                    ? DB::table($resultTable)->where($resultForeignKey, $fixtureId)->count()
                    : 0,
            ];
        })->values()->all();
    }

    private function referenceState(int $keepId, int $removeId): array
    {
        $impact = [];
        $fingerprints = [];
        foreach ($this->availableReferences() as $table => $columns) {
            foreach ($columns as $column) {
                foreach (['keep' => $keepId, 'remove' => $removeId] as $side => $playerId) {
                    $rows = DB::table($table)->where($column, $playerId)->get()->map(fn ($row) => (array) $row);
                    $hashes = $rows->map(function ($row) {
                        ksort($row);
                        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
                    })->sort()->values()->all();
                    $impact[$table][$column][$side] = $rows->count();
                    $fingerprints[$table][$column][$side] = $hashes;
                }
            }
        }

        return compact('impact', 'fingerprints');
    }

    private function registrationHistoryState(int $keepId, int $removeId): array
    {
        $impact = [];
        $fingerprints = [];
        if (! Schema::hasTable('player_registrations')) {
            return compact('impact', 'fingerprints');
        }

        $registrationIds = [
            'keep' => DB::table('player_registrations')->where('player_id', $keepId)->pluck('registration_id'),
            'remove' => DB::table('player_registrations')->where('player_id', $removeId)->pluck('registration_id'),
        ];
        foreach (self::REGISTRATION_HISTORY_REFERENCES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                foreach ($registrationIds as $side => $ids) {
                    $rows = $ids->isEmpty() ? collect() : DB::table($table)->whereIn($column, $ids)->get();
                    $hashes = $rows->map(function ($row) {
                        $values = (array) $row;
                        ksort($values);
                        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
                    })->sort()->values()->all();
                    $impact[$table][$column][$side] = $rows->count();
                    $fingerprints[$table][$column][$side] = $hashes;
                }
            }
        }

        return compact('impact', 'fingerprints');
    }

    private function sourceUserIds(Player $player): Collection
    {
        $userIds = Schema::hasTable('user_players')
            ? DB::table('user_players')->where('player_id', $player->id)->pluck('user_id') : collect();
        if ($player->userId) {
            $userIds->push((int) $player->userId);
        }

        return $userIds->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function playerSnapshot(Player $player): array
    {
        return $player->only([
            'id', 'name', 'surname', 'email', 'cellNr', 'dateOfBirth', 'gender', 'coach',
            'userId', 'profile_updated_at', 'profile_complete', 'created_at', 'updated_at',
        ]);
    }

    private function stablePlayerSnapshot(Player $player): array
    {
        $columns = [
            'id', 'name', 'surname', 'email', 'cellNr', 'dateOfBirth', 'gender', 'coach',
            'userId', 'profile_updated_at', 'profile_complete', 'created_at', 'updated_at',
        ];

        return (array) DB::table('players')->where('id', $player->id)->first($columns);
    }

    private function serializableImpact(array $impact): array
    {
        foreach (['keep', 'remove'] as $side) {
            if (isset($impact[$side]['player'])) {
                $impact[$side]['player'] = $this->playerSnapshot($impact[$side]['player']);
            }
            foreach (['owners', 'emails'] as $key) {
                if (($impact[$side][$key] ?? null) instanceof Collection) {
                    $impact[$side][$key] = $impact[$side][$key]->all();
                }
            }
        }

        return $impact;
    }

    private function identityNameKey(Player $player): string
    {
        return $this->normalizeName((string) $player->name).'|'.$this->normalizeName((string) $player->surname);
    }

    private function normalizeName(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    private function normalizedContact(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizedCell(?string $value): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $value) ?? '';
    }

    private function fieldValue(Player $player, string $field): ?string
    {
        $value = $player->{$field};
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value === null ? null : (string) $value;
    }
}
