<?php

namespace App\Console\Commands;

use App\Models\PlayerPlusOrder;
use App\Services\Performance\{PlayerPlusNotifications, PlayerPlusService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PlayerPlusNotificationsCommand extends Command
{
    protected $signature = 'player-plus:notifications {--apply : Reserve managed messages for delivery} {--order= : Review one existing order for explicit notification repair} {--limit=200 : Maximum orders per message type, from 1 to 500}';
    protected $description = 'Prepare missing Plus confirmations, expiry reminders and reconciliation alerts in bounded batches';

    public function handle(PlayerPlusNotifications $notifications): int
    {
        if (! PlayerPlusService::ready()) { $this->warn('Player Plus schema is not ready.'); return self::SUCCESS; }
        if ($this->option('order') !== null) {
            $id = filter_var($this->option('order'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! $id || ! ($order = PlayerPlusOrder::find($id))) { $this->error('Order not found.'); return self::INVALID; }
            $this->line('Order P'.$order->id.' selected for managed notification review.');
            if ($this->option('apply')) foreach (PlayerPlusNotifications::TYPES as $kind) $notifications->record($order, $kind, true);
            else $this->info('Dry run only. No messages reserved or sent.');
            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($limit === false) { $this->error('Limit must be between 1 and 500.'); return self::INVALID; }
        $cache = Cache::store(app()->environment('testing') ? 'array' : 'file');
        foreach (PlayerPlusNotifications::TYPES as $kind) {
            $query = $kind === 'reconciliation'
                ? PlayerPlusOrder::whereNotNull('reconciliation_reason')
                : PlayerPlusOrder::verifiedSettlement()->whereDoesntHave('mailLogs', fn ($logs) => $logs->where('mail_type', 'player_plus_'.$kind));
            if ($kind === 'renewal') {
                if (! PlayerPlusService::salesEnabled()) continue;
                $query->where('term_ends_at', '>', now())->where('term_ends_at', '<=', now()->addDays(14))
                    ->whereHas('currentMembership', fn ($query) => $query->whereColumn('player_plus_memberships.player_id', 'player_plus_orders.player_id'));
            }
            $cursorKey = 'player-plus:notification-cursor:'.$kind;
            $cursor = max(0, (int) $cache->get($cursorKey, 0));
            $orders = (clone $query)->where('id', '>', $cursor)->orderBy('id')->limit($limit)->get();
            if ($orders->isEmpty() && $cursor > 0) {
                $orders = (clone $query)->orderBy('id')->limit($limit)->get();
            }
            $this->line($kind.': '.$orders->count().' candidate orders.');
            if ($this->option('apply')) {
                foreach ($orders as $order) $notifications->record($order, $kind);
                // Advance past invalid/undeliverable records too; wrap on a later pass so contact
                // corrections and missing alert reservations are eventually reconsidered.
                $cache->forever($cursorKey, $orders->last()?->id ?? 0);
            }
            if ($orders->count() === $limit) $this->warn($kind.': batch limit reached; further passes continue by order ID.');
        }
        if (! $this->option('apply')) $this->info('Dry run only. No messages reserved or sent.');
        return self::SUCCESS;
    }
}
