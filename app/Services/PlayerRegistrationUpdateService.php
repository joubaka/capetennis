<?php

namespace App\Services;

use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, CategoryEventRegistration, Player, RegistrationOrder, SiteSetting, TeamPaymentOrder, TeamPlayer};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Participant updates deliberately contain no receipt, amount or private checkout link. */
final class PlayerRegistrationUpdateService
{
    public function order(RegistrationOrder $order): void
    {
        foreach ($order->items as $item) {
            $entry = CategoryEventRegistration::where('registration_id', $item->registration_id)
                ->where('category_event_id', $item->category_event_id)->first();
            if ($entry) $this->record($entry);
        }
    }

    public function record(CategoryEventRegistration|TeamPaymentOrder $source, string $action = 'registration'): void
    {
        DB::afterCommit(function () use ($source, $action): void {
            $source = $source->fresh();
            if (! $source) return;
            foreach ($this->messages($source, $action) as $message) {
                app(BulkMailDispatcher::class)->dispatch('player_registration_update', $source,
                    [['email' => $message['email'], 'name' => $message['name']]],
                    ['event_id' => $message['event_id'], 'player_id' => $message['player_id'], 'action' => $action,
                        'campaign_key' => 'participant:'.$source->getMorphClass().':'.$source->id.':'.$action.':'.$message['player_id'],
                        'subject' => $message['subject'], 'body' => $message['body'], 'system_initiated' => true,
                        'manual_retry_only' => true]);
            }
        });
    }

    private function messages(Model $source, string $action): array
    {
        if (! in_array($action, ['registration', 'withdrawal'], true)) return [];
        if ($source instanceof CategoryEventRegistration) {
            $setting = $action === 'registration'
                ? ($source->admin_payment_status !== null ? 'player_email_on_admin_entry' : 'player_email_on_registration')
                : 'player_email_on_withdrawal';
            if (! SiteSetting::emailEnabled($setting)) return [];
            $source->load(['categoryEvent.event', 'categoryEvent.category', 'players.user', 'players.users', 'user']);
            $event = $source->categoryEvent?->event;
            $players = $source->players;
            $category = $source->categoryEvent?->category?->name;
            $withdrawn = $source->withdrawn_at || str_starts_with((string) $source->status, 'withdrawn');
            if ($action === 'registration' && ($withdrawn || (int) $source->payment_status_id !== 1 || $source->status === 'pending_checkout')) return [];
        } elseif ($source instanceof TeamPaymentOrder) {
            if (! SiteSetting::emailEnabled($action === 'registration' ? 'player_email_on_team_registration' : 'player_email_on_team_withdrawal')) return [];
            $source->load(['event', 'team', 'effectivePlayer.user', 'effectivePlayer.users', 'user']);
            $event = $source->event;
            if (! $event) return [];
            $players = collect([$source->effectivePlayer])->filter();
            $category = $source->team?->name;
            $withdrawn = (bool) $source->withdrawn_at;
            if ($action === 'registration' && ($withdrawn || (int) $source->getRawOriginal('pay_status') !== 1
                || ! $source->team || ! app(EventTeamScope::class)->query($event)->whereKey($source->team_id)->exists()
                || ! TeamPlayer::withoutGlobalScopes()->where('team_id', $source->team_id)->where('player_id', $source->effective_player_id)->where('pay_status', 1)->exists())) return [];
        } else return [];
        if (! $event || ($action === 'withdrawal' && ! $withdrawn)) return [];

        $payerEmail = mb_strtolower(trim((string) $source->user?->email));
        return $players->flatMap(function (Player $player) use ($event, $category, $payerEmail, $action) {
            $subject = ($action === 'registration' ? 'Registration confirmed' : 'Withdrawal confirmed').' – '.$event->name;
            $body = '<p>'.e($player->full_name).($action === 'registration' ? ' is registered for ' : ' has withdrawn from ').e($event->name)
                .($category ? ' ('.e($category).')' : '').'.</p><p><a href="'.e(route('events.show', $event)).'">View event</a></p>';
            $additional = collect([$player->email_2, $player->email_3])->map(fn ($email) => mb_strtolower(trim((string) $email)));
            return app(TeamSelectionContactService::class)->emails($player)->intersect($additional)->reject(fn ($email) => $email === $payerEmail)
                ->map(fn ($email) => ['email' => $email, 'name' => $player->full_name, 'player_id' => $player->id,
                    'event_id' => $event->id, 'subject' => $subject, 'body' => $body]);
        })->values()->all();
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        if ($log->mail_type !== 'player_registration_update') return null;
        $source = match ($log->related_type) {
            CategoryEventRegistration::class => CategoryEventRegistration::find($log->related_id),
            TeamPaymentOrder::class => TeamPaymentOrder::find($log->related_id),
            default => null,
        };
        if (! $source) return null;
        foreach ($this->messages($source, $log->payload['action'] ?? '') as $message) {
            if ($message['email'] === $log->recipient_email && $message['player_id'] === (int) ($log->payload['player_id'] ?? 0)
                && $message['event_id'] === (int) ($log->payload['event_id'] ?? 0)
                && $message['subject'] === ($log->payload['subject'] ?? null) && $message['body'] === ($log->payload['body'] ?? null)) {
                return (new BulkEventMail($message['subject'], $message['body'], config('mail.from.name', 'Cape Tennis'), config('mail.from.address')))
                    ->with('player_registration_update_log_id', $log->id);
            }
        }
        return null;
    }
}
