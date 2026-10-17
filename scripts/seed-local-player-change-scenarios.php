<?php

declare(strict_types=1);

/** Local synthetic sandbox only. Run with --apply once; later runs only report it. */
use App\Domain\Payments\Services\{LedgerService, TeamPaymentService};
use App\Models\{Category, CategoryEvent, Draw, Event, EventRegion, EventType, NoProfileTeamPlayer, Player, Team, TeamPaymentOrder, TeamPlayer, TeamRegion, TeamTie, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{DB, Event as Events, Http, Mail, Notification, Queue};

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
if (PHP_SAPI !== 'cli' || !$app->environment('local') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'ct'
    || !in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)
    || !in_array(strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)), ['127.0.0.1', 'localhost', '::1', '[::1]'], true)) {
    throw new RuntimeException('Refusing: this script requires CLI, APP_ENV=local, loopback MySQL ct and a loopback APP_URL.');
}

$name = 'LOCAL TEST - Player changes sandbox 2026-10-16';
$lockedName = 'LOCAL TEST - Player changes fixture lock 2026-10-16';
$marker = 'local-player-change-sandbox-v1-20261016';
$existing = Event::where('name', $name)->first();
$apply = in_array('--apply', $argv, true);
if (!$existing && !$apply) {
    echo "Guard passed: local loopback ct. No writes. Run with --apply to create the isolated synthetic sandbox.\n";
    exit(0);
}

// These fakes apply only to this CLI process, never to application configuration.
Mail::fake(); Queue::fake(); Notification::fake(); Http::preventStrayRequests();
Events::fake([App\Events\PaymentCompleted::class, App\Events\PaymentFailed::class,
    App\Events\WalletCredited::class, App\Events\WalletDebited::class,
    Illuminate\Auth\Events\Registered::class]);

if (!$existing) {
    $existing = DB::transaction(function () use ($name, $lockedName, $marker): Event {
        if (Event::where('name', $name)->lockForUpdate()->exists()) throw new RuntimeException('Sandbox already exists; rerun without --apply.');
        if (Event::where('name', $lockedName)->exists() || User::where('email', $marker.'@example.invalid')->exists()
            || TeamRegion::where('region_name', 'LOCAL TEST Eden - Player changes')->exists()) {
            throw new RuntimeException('A partial or conflicting sandbox exists. Refusing to overwrite or reset anything.');
        }
        $type = EventType::findOrFail(3);
        if ((int) $type->type !== EventType::TEAM) throw new RuntimeException('Expected normal team event type 3.');
        $payer = User::create(['name' => 'LOCAL TEST Synthetic payer', 'email' => $marker.'@example.invalid',
            'password' => password_hash(bin2hex(random_bytes(64)), PASSWORD_BCRYPT),
            'userName' => 'LOCAL TEST', 'userSurname' => 'Synthetic payer', 'cell_nr' => null]);
        $wallet = $payer->wallet()->create([]);
        $region = TeamRegion::create(['region_name' => 'LOCAL TEST Eden - Player changes', 'short_name' => 'LOCAL', 'region_fee' => 0]);
        $category = Category::create(['name' => 'LOCAL TEST Open player changes', 'Fee' => 0]);
        $makeEvent = function (string $eventName) use ($marker, $region): Event {
            $event = Event::create(['name' => $eventName, 'information' => '<p>'.$marker.'; synthetic local fixtures only. No real payment or public registration.</p>',
                'start_date' => now()->addMonths(2)->toDateString(), 'end_date' => now()->addMonths(2)->addDays(2)->toDateString(),
                'deadline' => 7, 'withdrawal_deadline' => now()->addMonth(), 'eventType' => 3, 'entryFee' => 100,
                'logo' => '', 'email' => 'local-player-changes@example.invalid', 'organizer' => 'LOCAL TEST', 'status' => 'active',
                'published' => false, 'signUp' => false, 'results_published' => false, 'result_notifications_enabled' => false]);
            $er = new EventRegion(); $er->forceFill(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1])->save();
            return $event;
        };
        $event = $makeEvent($name);
        $locked = $makeEvent($lockedName);
        $makeCategory = fn (Event $e) => CategoryEvent::create(['event_id' => $e->id, 'category_id' => $category->id, 'entry_fee' => 100, 'ordering' => 1, 'nominations_published' => false]);
        $ce = $makeCategory($event); $lockedCe = $makeCategory($locked);
        $makeTeam = fn (string $label, bool $raw = true, ?CategoryEvent $scope = null) => Team::create([
            'name' => 'LOCAL TEST '.$label, 'num_team_members' => 5, 'year' => (int) now()->addMonths(2)->year,
            'published' => false, 'user_id' => $payer->id, 'personal_team' => false, 'region_id' => $region->id,
            'category_event_id' => ($scope ?? $ce)->id, 'noProfile' => $raw]);
        $sequence = 0;
        $makePlayer = function (string $first, string $last = 'Sandbox') use (&$sequence, $marker): Player {
            return Player::create(['name' => $first, 'surname' => $last, 'email' => $marker.'-'.(++$sequence).'@example.invalid',
                'cellNr' => '', 'gender' => 1, 'dateOfBirth' => '2014-06-15', 'userId' => 0,
                'profile_complete' => true, 'profile_updated_at' => now()]);
        };
        $payments = app(TeamPaymentService::class);
        $raw = function (Team $team, int $rank, string $first, string $last = 'Sandbox', ?Player $player = null) use ($payments): NoProfileTeamPlayer {
            $slot = NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank, 'name' => $first, 'surname' => $last,
                'email' => null, 'cell_nr' => null, 'pay_status' => 0, 'player_profile' => $player?->id]);
            if ($player) $payments->createUnpaidTeamPlayerSlot($team, $player, $rank);
            return $slot;
        };
        $team = $makeTeam('01 Raw unpaid - add remove order');
        $raw($team, 1, 'RawFirst'); $raw($team, 2, 'RawSecond'); $raw($team, 3, 'RawThird');
        $team = $makeTeam('02 Linked unpaid - blank name reset');
        $raw($team, 2, 'ImportedOld', 'Different', $makePlayer('LinkedVisible'));
        $raw($team, 3, 'Duplicate', 'Target'); // Rank 1 is deliberately vacant.
        $raw($team, 4, 'SiblingUntouched');
        $team = $makeTeam('03 Linked paid - must reject change');
        $paidRawPlayer = $makePlayer('LinkedPaid'); $raw($team, 1, 'ImportedPaid', 'Different', $paidRawPlayer);
        $raw($team, 2, 'PaidSibling');
        $paidRawOrder = $payments->ensureOrder($payer, $team, $paidRawPlayer, $event, (float) $event->entryFee);
        $team = $makeTeam('04 Reserved unpaid - release on change');
        $reserved = $makePlayer('ReservedUnpaid'); $raw($team, 2, 'ImportedReserved', 'Different', $reserved); $raw($team, 3, 'ReservedSibling');
        $reservedOrder = $payments->ensureOrder($payer, $team, $reserved, $event, (float) $event->entryFee);
        $payments->reservePayment($reservedOrder, 40, 60);
        $team = $makeTeam('05 Handoff - must reject change');
        $handoff = $makePlayer('HandoffProtected'); $raw($team, 1, 'ImportedHandoff', 'Different', $handoff); $raw($team, 2, 'HandoffSibling');
        $order = $payments->ensureOrder($payer, $team, $handoff, $event, (float) $event->entryFee);
        $payments->recordPayfastHandoff($order, $payer, (float) $order->payfast_amount_due); // Records local state; no provider call.
        $team = $makeTeam('06 Profile unpaid - search replacement', false);
        $payments->createUnpaidTeamPlayerSlot($team, $makePlayer('ProfileUnpaid'), 2);
        $payments->createUnpaidTeamPlayerSlot($team, $makePlayer('ProfileSibling'), 3);
        $makePlayer('LocalCandidateAlpha'); $makePlayer('LocalCandidateBeta'); $makePlayer('LocalCandidateGamma');
        $team = $makeTeam('07 Profile paid - must reject change', false);
        $paidProfile = $makePlayer('ProfilePaid'); $payments->createUnpaidTeamPlayerSlot($team, $paidProfile, 1);
        $payments->createUnpaidTeamPlayerSlot($team, $makePlayer('ProfilePaidSibling'), 2);
        $paidProfileOrder = $payments->ensureOrder($payer, $team, $paidProfile, $event, (float) $event->entryFee);
        $team = $makeTeam('08 Raw typo - name correction limits');
        $raw($team, 1, 'Typoo', 'Unlinked'); $raw($team, 2, 'TypoSibling');
        $team = $makeTeam('09 Empty raw - add first player');
        $team = $makeTeam('10 Empty profile - search first player', false);
        $team = $makeTeam('11 Full raw - capacity and duplicate');
        foreach (range(1, 5) as $rank) $raw($team, $rank, 'Full'.$rank);
        $home = $makeTeam('90 Fixture lock home', true, $lockedCe); $raw($home, 1, 'LockedHome');
        $away = $makeTeam('91 Fixture lock away', true, $lockedCe); $raw($away, 1, 'LockedAway');
        $draw = Draw::create(['drawName' => 'LOCAL TEST private fixture guard', 'event_id' => $locked->id, 'category_event_id' => $lockedCe->id, 'locked' => false, 'published' => false]);
        TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => TeamTie::STATUS_DRAFT]);
        // Simulated money exists only on this new synthetic payer's wallet.
        app(LedgerService::class)->appendWalletCredit($wallet, 500, 'local_player_change_fixture', $event->id, ['local_only' => true, 'marker' => $marker]);
        foreach ([$paidRawOrder, $paidProfileOrder] as $paidOrder) {
            $payments->reservePayment($paidOrder, (float) $paidOrder->total_amount, 0);
            $payments->finalizeWalletPayment($paidOrder->fresh(), ['wallet_meta' => ['local_only' => true, 'marker' => $marker]]);
        }
        return $event;
    });
}

if (!str_contains((string) $existing->information, $marker)) throw new RuntimeException('Existing event has no matching sandbox marker. Refusing.');
$events = Event::whereIn('name', [$name, $lockedName])->get();
$teamIds = Team::whereIn('category_event_id', CategoryEvent::whereIn('event_id', $events->pluck('id'))->select('id'))->pluck('id');
$payer = User::where('email', $marker.'@example.invalid')->firstOrFail();
$orders = TeamPaymentOrder::whereIn('event_id', $events->pluck('id'))->get();
$result = ['created_or_existing' => $existing->id, 'local_only' => true,
    'events' => $events->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'published' => (bool) $e->published, 'signUp' => (bool) $e->signUp,
        'teams_url' => route('admin.events.teams', $e, false),
        'preview_url' => route('backend.team-selection.manager.preview', [$e, EventRegion::where('event_id', $e->id)->firstOrFail()], false),
        'event_region_id' => EventRegion::where('event_id', $e->id)->value('id')])->all(),
    'teams' => Team::whereIn('id', $teamIds)->orderBy('name')->get(['id', 'name', 'noProfile'])
        ->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'noProfile' => (bool) $team->noProfile])->all(),
    'counts' => ['teams' => $teamIds->count(), 'raw_slots' => NoProfileTeamPlayer::whereIn('team_id', $teamIds)->count(),
        'profile_or_mirror_slots' => TeamPlayer::whereIn('team_id', $teamIds)->count(), 'orders' => $orders->count(),
        'paid_orders' => $orders->where('pay_status', true)->count(), 'wallet_transactions' => $payer->wallet->transactions()->count()],
    'orders' => $orders->map(fn ($o) => ['id' => $o->id, 'team_id' => $o->team_id, 'player_id' => $o->player_id, 'paid' => (bool) $o->pay_status,
        'total' => (float) $o->total_amount, 'reserved' => (float) $o->wallet_reserved, 'due' => (float) $o->payfast_amount_due, 'handoff' => $o->payfast_handed_off_at !== null])->all(),
    'synthetic_wallet_balance' => $payer->wallet->balance,
    'outbound' => ['mail_transport' => 'process-local fake; payment notification events intercepted', 'jobs_intercepted' => Queue::pushedJobs() ? count(Queue::pushedJobs()) : 0],
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
