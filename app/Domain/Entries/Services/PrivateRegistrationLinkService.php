<?php

namespace App\Domain\Entries\Services;

use App\Models\{CategoryEvent, Event, Player, PrivateRegistrationLink, RegistrationOrder, RegistrationOrderItems, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/** Admission for one ordinary individual checkout; never grants payer or player ownership. */
class PrivateRegistrationLinkService
{
    public function authorize(Event $event, CategoryEvent $category, User $actor): void
    {
        abort_unless((int) $category->event_id === (int) $event->id, 404);
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
    }

    public function assertCategory(CategoryEvent $category): void
    {
        $event = $category->event;
        $this->assertEvent($event);
        if ($category->isLocked()) {
            $this->reject('This category is unavailable for private registration.');
        }
    }

    private function assertEvent(?Event $event): void
    {
        if (!$event || !$event->isIndividual() || $event->isMasters() || $event->isInterprovincialTrials() || $event->isCamp()
            || app(\App\Services\CavaliersNominationEligibility::class)->applies($event)) {
            $this->reject('Private registration links support ordinary individual events only.');
        }
        if (!$event->published || $event->status === 'draft' || !$event->start_date || now()->gte(\Illuminate\Support\Carbon::parse($event->start_date)->startOfDay())) {
            $this->reject('This category is unavailable for private registration.');
        }
    }

    public function issue(Event $event, CategoryEvent $category, Player $player, User $actor, string $reviewedEmail, string $reviewedContacts): array
    {
        return DB::transaction(function () use ($event, $category, $player, $actor, $reviewedEmail, $reviewedContacts) {
            $category = CategoryEvent::with('event.eventTypeModel')->lockForUpdate()->findOrFail($category->id);
            $this->authorize($event, $category, $actor);
            $this->assertCategory($category);
            if (!DB::table('user_players')->where('player_id', $player->id)->exists()) {
                $this->reject('This player has no linked account. Link the player to their existing account before enabling private registration.');
            }
            app(EntryEligibilityService::class)->assertCanRegister($category, $player->id);
            app(\App\Services\DivisionCapacityService::class)->assertAvailable($category);
            $contacts = app(\App\Services\PrivateRegistrationInvitationService::class)->contacts($player->fresh());
            if (!$contacts['primary'] || $contacts['primary'] !== $reviewedEmail || !hash_equals($contacts['fingerprint'], $reviewedContacts)) {
                $this->reject('The player contacts are missing, invalid or changed. Review all recipients before enabling registration.');
            }
            $existing = PrivateRegistrationLink::where('category_event_id', $category->id)->where('player_id', $player->id)
                ->whereNull('order_id')->where('expires_at', '>', now())->orderByDesc('id')->lockForUpdate()->first();
            if ($existing) {
                $log = \App\Models\BulkEmailLog::where('mail_type', 'private_registration_invitation')
                    ->where('related_type', PrivateRegistrationLink::class)->where('related_id', $existing->id)->latest('id')->first();
                if ($log && app(\App\Services\PrivateRegistrationInvitationService::class)->authorizedMailable($log)) {
                    return $this->issuedResult($existing, true);
                }
                // Contact, issuer or reviewed content changed. Retire the unused
                // admission before issuing one fresh invitation; old jobs skip.
                PrivateRegistrationLink::where('category_event_id', $category->id)->where('player_id', $player->id)
                    ->whereNull('order_id')->where('expires_at', '>', now())->update(['expires_at' => now()]);
            }
            $this->assertNoPendingCheckout(new PrivateRegistrationLink(['category_event_id' => $category->id, 'player_id' => $player->id]));
            $expires = now()->addHours(48)->min(\Illuminate\Support\Carbon::parse($category->event->start_date)->startOfDay());
            $link = PrivateRegistrationLink::create(['category_event_id' => $category->id,
                'player_id' => $player->id, 'issued_by' => $actor->id, 'expires_at' => $expires]);
            activity('registration')->performedOn($link)->causedBy($actor)
                ->withProperties(['category_event_id' => $category->id, 'player_id' => $player->id, 'expires_at' => $expires->toIso8601String()])
                ->log('Private registration link issued');
            app(\App\Services\PrivateRegistrationInvitationService::class)->queue($link);
            return $this->issuedResult($link, false);
        });
    }

    private function issuedResult(PrivateRegistrationLink $link, bool $reused): array
    {
        $log = \App\Models\BulkEmailLog::where('mail_type', 'private_registration_invitation')
            ->where('related_type', PrivateRegistrationLink::class)->where('related_id', $link->id)
            ->where('payload->recipient_kind', 'primary-player')->latest('id')->first();
        $messages = \App\Models\BulkEmailLog::where('mail_type', 'private_registration_invitation')
            ->where('related_type', PrivateRegistrationLink::class)->where('related_id', $link->id)->get(['recipient_email', 'status', 'payload']);
        return ['url' => route('registration.private.show', $link->id), 'expires' => $link->expires_at, 'reused' => $reused,
            'recipient' => $log?->recipient_email, 'mail_status' => $log?->status ?? 'missing', 'messages' => $messages];
    }

    public function resolveById(int $id, bool $lock = false): PrivateRegistrationLink
    {
        $link = PrivateRegistrationLink::with(['categoryEvent.event.eventTypeModel', 'player', 'issuer'])
            ->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        return $this->validateLink($link, $lock);
    }

    public function assertLinkedAccount(PrivateRegistrationLink $link, User $user, bool $lock = false): void
    {
        $association = DB::table('user_players')->where('user_id', $user->id)->where('player_id', $link->player_id)
            ->when($lock, fn ($q) => $q->lockForUpdate())->first(['id']);
        abort_unless($association, 403, 'Sign in with an account already linked to this player.');
    }

    /** Filter account/event/lifecycle/issuer/order scope in SQL before the bounded list. */
    public function forEvent(Event $event, ?User $user): Collection
    {
        if (!$user || \App\Models\SiteSetting::get('registration_open', '1') !== '1' || $event->registrationIsOpen()) {
            return collect();
        }
        try {
            $this->assertEvent($event);
        } catch (ValidationException) {
            return collect();
        }

        $query = PrivateRegistrationLink::query()->where('expires_at', '>', now())
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('user_players')
                ->whereColumn('user_players.player_id', 'private_registration_links.player_id')->where('user_players.user_id', $user->id))
            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id)->whereNull('locked_at')->whereNull('cancelled_at'))
            ->whereHas('issuer', fn ($q) => $q->whereHas('roles', fn ($roles) => $roles->where('name', 'super-user'))
                ->orWhere(fn ($admin) => $admin->whereHas('roles', fn ($roles) => $roles->where('name', 'admin'))
                    ->whereHas('adminEvents', fn ($events) => $events->where('events.id', $event->id))))
            ->where(fn ($q) => $q->whereNull('order_id')->orWhereHas('order', fn ($orders) => $orders->where('user_id', $user->id)
                ->where('pay_status', false)->where(fn ($pending) => $pending->whereNull('status')->orWhereNotIn('status', ['cancelled', 'expired']))));
        $latestIds = $query->selectRaw('MAX(private_registration_links.id)')->groupBy('category_event_id', 'player_id');
        return PrivateRegistrationLink::with(['player:id,name,surname', 'categoryEvent.category'])
            ->whereIn('id', $latestIds)->orderByDesc('id')->limit(20)->get();
    }

    private function validateLink(PrivateRegistrationLink $link, bool $lock): PrivateRegistrationLink
    {
        if ($lock && $link->categoryEvent) {
            // Locking reads see publication/cancellation changes committed while
            // this checkout was waiting for the division, including MySQL RR.
            $category = CategoryEvent::lockForUpdate()->findOrFail($link->category_event_id);
            $category->setRelation('event', Event::sharedLock()->findOrFail($category->event_id));
            $link->setRelation('categoryEvent', $category);
        }
        if ($link->expires_at->lte(now()) || !$link->issuer || !$link->player || !$link->categoryEvent) {
            $this->reject('This registration link is no longer available. Contact the event organiser.');
        }
        $this->authorize($link->categoryEvent->event, $link->categoryEvent, $link->issuer);
        $this->assertCategory($link->categoryEvent);
        return $link;
    }

    public function assertCart(PrivateRegistrationLink $link, array $players, array $categories): void
    {
        if (array_map('intval', $players) !== [(int) $link->player_id] || array_map('intval', $categories) !== [(int) $link->category_event_id]) {
            $this->reject('This link is valid only for its selected player and category.');
        }
    }

    public function resume(PrivateRegistrationLink $link, User $payer): ?RedirectResponse
    {
        if (!$link->order_id) {
            return null;
        }
        $order = RegistrationOrder::findOrFail($link->order_id);
        abort_unless((int) $order->user_id === (int) $payer->id, 403, 'This registration link has already been used.');
        return redirect()->route('registration.checkout', $order);
    }

    public function assertNoPendingCheckout(PrivateRegistrationLink $link): void
    {
        if (RegistrationOrderItems::where('category_event_id', $link->category_event_id)->where('player_id', $link->player_id)
            ->whereHas('order', fn ($q) => $q->where('pay_status', 0)->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['cancelled', 'expired'])))
            ->lockForUpdate()->first(['id'])) {
            $this->reject('A checkout for this player and category is already in progress. Contact the event organiser.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['private_registration_link_id' => $message]);
    }
}
