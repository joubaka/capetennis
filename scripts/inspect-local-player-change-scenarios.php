<?php

declare(strict_types=1);

use App\Models\{CategoryEvent, Event, NoProfileTeamPlayer, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (PHP_SAPI !== 'cli' || !$app->environment('local') || $db->getDriverName() !== 'mysql'
    || $db->getDatabaseName() !== 'ct' || !in_array($db->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Local loopback ct only.');
}
$events = Event::whereIn('id', [258, 259])->get();
if ($events->count() !== 2 || $events->contains(fn ($event) => !str_starts_with($event->name, 'LOCAL TEST - Player changes')
    || !str_contains((string) $event->information, 'local-player-change-sandbox-v1-20261016'))) {
    throw new RuntimeException('Expected synthetic sandbox markers are missing.');
}
$teams = Team::whereIn('category_event_id', CategoryEvent::whereIn('event_id', $events->pluck('id'))->select('id'))->orderBy('id')->get();
$ids = $teams->pluck('id');
$payer = User::where('email', 'local-player-change-sandbox-v1-20261016@example.invalid')->firstOrFail();
$orders = TeamPaymentOrder::whereIn('event_id', $events->pluck('id'))->orderBy('id')->get();
echo json_encode([
    'captured_at' => now()->toIso8601String(), 'events' => $events->pluck('id'),
    'teams' => $teams->map(fn ($team) => ['id' => $team->id, 'name' => $team->name, 'noProfile' => (bool) $team->noProfile]),
    'raw_slots' => NoProfileTeamPlayer::whereIn('team_id', $ids)->orderBy('team_id')->orderBy('rank')
        ->get(['id', 'team_id', 'rank', 'name', 'surname', 'player_profile', 'pay_status'])->toArray(),
    'mirrors' => TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $ids)->orderBy('team_id')->orderBy('rank')
        ->get(['id', 'team_id', 'rank', 'player_id', 'pay_status'])->toArray(),
    'orders' => $orders->map(fn ($order) => ['id' => $order->id, 'team_id' => $order->team_id, 'player_id' => $order->player_id,
        'total' => $order->total_amount, 'paid' => $order->pay_status, 'reserved' => $order->wallet_reserved,
        'due' => $order->payfast_amount_due, 'wallet_debited' => $order->wallet_debited, 'payfast_paid' => $order->payfast_paid,
        'handoff' => $order->payfast_handed_off_at, 'withdrawn' => $order->withdrawn_at]),
    'wallet' => ['balance' => $payer->wallet->balance, 'transactions' => $payer->wallet->transactions()->orderBy('id')
        ->get(['id', 'type', 'amount', 'source_type', 'source_id'])->toArray()],
    'audit_count' => DB::table('activity_log')->where('subject_type', Team::class)->whereIn('subject_id', $ids)->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
