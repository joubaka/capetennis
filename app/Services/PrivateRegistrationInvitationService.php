<?php

namespace App\Services;

use App\Domain\Entries\Services\{EntryEligibilityService, PrivateRegistrationLinkService};
use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, Player, PrivateRegistrationLink};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Validation\ValidationException;

/** Actionable primary invitation and link-free contact copies in the managed outbox. */
class PrivateRegistrationInvitationService
{
    public function recipient(Player $player): ?string
    {
        $email = mb_strtolower(trim((string) $player->email));
        return app(TeamSelectionContactService::class)->emails($player)->contains($email) ? $email : null;
    }

    public function contacts(Player $player): array
    {
        $primary = $this->recipient($player);
        $copies = app(TeamSelectionContactService::class)->emails($player)->reject(fn ($email) => $email === $primary)->sort()->values()->all();
        return ['primary' => $primary, 'copies' => $copies, 'fingerprint' => hash('sha256', json_encode([$primary, $copies], JSON_THROW_ON_ERROR))];
    }

    public function queue(PrivateRegistrationLink $link): void
    {
        $messages = $this->messages($link);
        if (!$messages) {
            throw ValidationException::withMessages(['player_id' => 'The private registration invitation is no longer eligible. Review the player and recipient again.']);
        }
        foreach ($messages as $content) {
            $stats = app(BulkMailDispatcher::class)->dispatch('private_registration_invitation', $link,
                [['email' => $content['email'], 'name' => $content['name']]],
                ['event_id' => $link->categoryEvent->event_id, 'player_id' => $link->player_id, 'created_by' => $link->issued_by,
                    'campaign_key' => 'private-registration:'.$link->id, 'recipient_kind' => $content['kind'],
                    'manual_retry_only' => true, 'invitation_fingerprint' => $content['fingerprint'],
                    'subject' => $content['subject'], 'body' => $content['body']] + $content['sender'], atomicDatabaseQueue: true);
            if ($stats['queued'] !== 1) {
                throw new \RuntimeException('The registration invitation could not be saved to the mail outbox. Nothing was enabled.');
            }
        }
    }

    private function messages(PrivateRegistrationLink $link): array
    {
        try {
            $link = app(PrivateRegistrationLinkService::class)->resolveById($link->id);
            if ($link->order_id || !\Illuminate\Support\Facades\DB::table('user_players')->where('player_id', $link->player_id)->exists()) return [];
            app(EntryEligibilityService::class)->assertCanRegister($link->categoryEvent, $link->player_id);
            app(DivisionCapacityService::class)->assertAvailable($link->categoryEvent);
            app(PrivateRegistrationLinkService::class)->assertNoPendingCheckout($link);
            $contacts = $this->contacts($link->player);
            $email = $contacts['primary'];
            if (!$email) return [];
            $event = $link->categoryEvent->event;
            $sender = CommunicationSender::eventDefaults($event, $link->issuer);
            $url = route('registration.private.show', $link->id);
            $body = '<p>Private registration has been enabled for '.e($link->player->full_name).' in '.e($link->categoryEvent->category->name).' at '.e($event->name).'.</p>'
                .'<p>Entry fee: R'.number_format($link->categoryEvent->effectiveEntryFee(), 2).'. Please register and pay before '.e($link->expires_at->format('j M Y H:i')).'. Your place is confirmed after payment.</p>'
                .'<p><a href="'.e($url).'">Register and pay</a></p>'
                .'<p>Sign in with a Cape Tennis account already linked to this player. You can also open the event page while signed in to find the registration option. Public entries remain closed.</p>';
            $messages = [['kind' => 'primary-player', 'email' => $email, 'name' => $link->player->full_name, 'subject' => 'Register and pay — '.$event->name,
                'body' => $body, 'sender' => $sender]];
            foreach ($contacts['copies'] as $copy) {
                $messages[] = ['kind' => 'player-notification', 'email' => $copy, 'name' => $link->player->full_name,
                    'subject' => 'Registration update — '.$event->name, 'sender' => $sender,
                    'body' => '<p>Private registration has been enabled for '.e($link->player->full_name).' in '.e($link->categoryEvent->category->name).' at '.e($event->name).'.</p>'
                        .'<p>The primary player contact receives the registration instructions. This is a notification only and does not grant registration access.</p>'];
            }
            foreach ($messages as &$content) {
                $content['fingerprint'] = hash('sha256', json_encode([$link->id, $link->player_id, $link->category_event_id, $link->issued_by, $contacts['fingerprint'], $content], JSON_THROW_ON_ERROR));
            }
            unset($content);
            return $messages;
        } catch (ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException|\RuntimeException $e) {
            return [];
        }
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        $link = $log->related_type === PrivateRegistrationLink::class ? PrivateRegistrationLink::find($log->related_id) : null;
        if ($log->mail_type !== 'private_registration_invitation' || !$link) return null;
        $content = collect($this->messages($link))->first(fn ($message) => $message['email'] === $log->recipient_email
            && $message['kind'] === ($log->payload['recipient_kind'] ?? null));
        if (!$content
            || (int) ($log->payload['event_id'] ?? 0) !== (int) $link->categoryEvent->event_id
            || (int) ($log->payload['player_id'] ?? 0) !== (int) $link->player_id
            || $log->recipient_email !== $content['email']
            || !hash_equals($content['fingerprint'], $log->payload['invitation_fingerprint'] ?? '')
            || ($log->payload['subject'] ?? null) !== $content['subject'] || ($log->payload['body'] ?? null) !== $content['body']) return null;
        foreach ($content['sender'] as $key => $value) {
            if (($log->payload[$key] ?? null) !== $value) return null;
        }
        return (new BulkEventMail($content['subject'], $content['body'], $content['sender']['from_name'],
            $content['sender']['reply_to'], $content['sender']['from_address']))->with('private_registration_invitation_log_id', $log->id);
    }
}
