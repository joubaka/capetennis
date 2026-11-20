<?php

namespace App\Services\Camps;

use App\Jobs\SendCampInvitation;
use App\Models\{BulkEmailLog, CampMailReview, CampNominee, PracticeCampPlan, User};
use App\Services\{InvitationMailSecurity, PracticeCampPlanner};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CampInvitations
{
    public function eventHash(CampNominee $nominee): string
    {
        return hash('sha256', json_encode([$nominee->event->only(['name', 'start_date', 'end_date', 'venue_notes', 'email', 'organizer', 'entryFee', 'published', 'signUp']),
            $nominee->categoryEvent?->effectiveEntryFee(), $nominee->categoryEvent?->category?->name], JSON_THROW_ON_ERROR));
    }
    public function participation(CampNominee $nominee): string
    {
        $entries = DB::table('category_event_registrations as entry')->join('player_registrations as player', 'player.registration_id', '=', 'entry.registration_id')
            ->where('entry.category_event_id', $nominee->category_event_id)->where('player.player_id', $nominee->player_id)
            ->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get(['entry.*']);
        $active = $entries->filter(fn ($entry) => empty($entry->withdrawn_at) && empty($entry->deleted_at)
            && ! in_array($entry->status, ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled'], true));
        if ($active->contains(fn ($entry) => (int) $entry->payment_status_id === 1)) return 'paid';
        $pendingQuery = DB::table('registration_order_items as item')->join('registration_orders as checkout', 'checkout.id', '=', 'item.order_id')
            ->where('item.category_event_id', $nominee->category_event_id)->where('item.player_id', $nominee->player_id)
            ->where('checkout.pay_status', 0)->where(fn ($q) => $q->whereNull('checkout.status')->orWhereNotIn('checkout.status', ['expired', 'cancelled']));
        $pending = DB::transactionLevel() > 0 ? $pendingQuery->lockForUpdate()->first(['item.id']) !== null : $pendingQuery->exists();
        $moves = DB::table('category_adjustments')->where('event_id', $nominee->event_id)
            ->where('target_category_id', $nominee->category_event_id)->where('player_id', $nominee->player_id)->where('status', 'pending');
        $pending = $pending || (DB::transactionLevel() > 0 ? $moves->lockForUpdate()->first(['id']) !== null : $moves->exists());
        $withdrawn = $entries->contains(fn ($entry) => ! empty($entry->withdrawn_at)
            || in_array($entry->status, ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded'], true)
            || ((int) $entry->payment_status_id === 1 && ! empty($entry->deleted_at)));
        return $pending ? 'checkout_pending' : ($withdrawn ? 'withdrawn' : $nominee->status);
    }

    public function token(string $token): CampNominee
    {
        abort_unless(strlen($token) === 64, 404);
        return CampNominee::where('token_hash', hash('sha256', $token))->with(['event', 'categoryEvent.category', 'player'])->firstOrFail();
    }

    public function decline(User $actor, string $token): void
    {
        $reference = $this->token($token);
        DB::transaction(function () use ($actor, $reference) {
            app(CampAdmission::class)->lockEvents([$reference->event_id]);
            \App\Models\CategoryEvent::whereKey($reference->category_event_id)->lockForUpdate()->firstOrFail();
            $nominee = CampNominee::lockForUpdate()->findOrFail($reference->id);
            if ($nominee->status === 'declined') return;
            abort_unless($nominee->status === 'approved', 422, 'This camp nomination is no longer active.');
            abort_unless($nominee->event->published && $nominee->event->registrationIsOpen(), 422, 'Camp decisions are closed.');
            $config = app(CampAdmission::class)->configuration($nominee->event_id);
            abort_if($config?->registration_closes_at && now()->gt($config->registration_closes_at), 422, 'Camp decisions are closed.');
            abort_if(in_array($this->participation($nominee), ['paid', 'checkout_pending'], true), 422, 'Resolve the existing entry or checkout before declining the camp invitation.');
            $nominee->update(['status' => 'declined', 'declined_at' => now(), 'declined_by' => $actor->id]);
            activity('camp')->performedOn($nominee)->causedBy($actor)->log('Declined camp invitation');
        });
    }

    private function snapshot(PracticeCampPlan $plan, array $ids, string $subject, string $message): array
    {
        $event = $plan->event;
        $config = app(CampAdmission::class)->configuration($event->id);
        abort_unless($config && $event->published && $event->registrationIsOpen(), 422, 'Publish the reviewed event and open registration before preparing entry invitations.');
        $nominees = CampNominee::where('event_id', $event->id)->whereIn('id', $ids)->where('status', 'approved')->with(['player', 'categoryEvent.category'])->orderBy('id')->get();
        abort_unless($nominees->count() === count($ids), 422, 'Choose approved nominees from this camp only.');
        $contacts = app(TeamSelectionContactService::class);
        $rows = [];
        foreach ($nominees as $nominee) {
            app(CampAdmission::class)->assertEligible($nominee->categoryEvent, (int) $nominee->player_id);
            abort_unless($nominee->categoryEvent && (int) $nominee->categoryEvent->event_id === (int) $event->id, 422);
            abort_if(in_array($this->participation($nominee), ['paid', 'checkout_pending', 'withdrawn'], true), 422, 'A selected player has entered or has an active checkout. Refresh the invitation selection.');
            $primary = $contacts->primaryEmail($nominee->player);
            if (! $primary) throw ValidationException::withMessages(['recipients' => $nominee->player->full_name.' has no actionable invitation email.']);
            $current = $contacts->emails($nominee->player)->sort()->values()->all();
            $copies = $contacts->emails($nominee->player)->intersect(collect([$nominee->player->email_2, $nominee->player->email_3])->map(fn ($email) => mb_strtolower(trim((string) $email))))->reject(fn ($email) => $email === $primary);
            foreach (collect([$primary])->merge($copies)->unique() as $email) {
                $notification = $email !== $primary;
                $url = $notification ? route('events.show', $event) : route('camp.invitation.show', $nominee->token_encrypted);
                $html = view('emails.camp-invitation', ['event' => $event, 'name' => $nominee->player->full_name, 'division' => $nominee->categoryEvent->category?->name,
                    'message' => $message, 'url' => $url, 'notification' => $notification])->render();
                $rows[] = ['nominee_id' => $nominee->id, 'name' => $nominee->player->full_name, 'email' => $email,
                    'notification_only' => $notification, 'contacts' => $current, 'primary' => $primary,
                    'subject' => $subject, 'html' => $html, 'configuration_revision' => $config->revision, 'event_hash' => $this->eventHash($nominee)];
            }
        }

        return ['ids' => $ids, 'subject' => $subject, 'message' => $message, 'rows' => $rows,
            'envelope' => ['from_address' => config('mail.from.address'), 'from_name' => config('mail.from.name'), 'reply_to' => $event->email ?: config('mail.from.address')]];
    }

    public function review(User $actor, PracticeCampPlan $plan, array $ids, string $subject, string $message): CampMailReview
    {
        app(PracticeCampPlanner::class)->authorizePlan($actor, $plan);
        $snapshot = $this->snapshot($plan, $ids, $subject, $message);
        return CampMailReview::create(['event_id' => $plan->event_id, 'actor_id' => $actor->id, 'snapshot' => $snapshot,
            'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'expires_at' => now()->addMinutes(20)]);
    }

    public function queue(User $actor, PracticeCampPlan $plan, CampMailReview $review): void
    {
        app(PracticeCampPlanner::class)->authorizePlan($actor, $plan);
        DB::transaction(function () use ($actor, $plan, $review) {
            app(CampAdmission::class)->lockEvents([$plan->event_id]);
            $review = CampMailReview::lockForUpdate()->findOrFail($review->id);
            abort_unless((int) $review->event_id === (int) $plan->event_id && (int) $review->actor_id === (int) $actor->id, 403);
            if ($review->queued_at) return;
            abort_if(now()->gt($review->expires_at), 409, 'This review expired. Review the current recipients and message again.');
            $s = $review->snapshot;
            $fresh = $this->snapshot($plan->fresh(), $s['ids'], $s['subject'], $s['message']);
            abort_unless(hash_equals($review->fingerprint, hash('sha256', json_encode($fresh, JSON_THROW_ON_ERROR))), 409, 'Contacts, nominees or event details changed. Prepare a new review.');
            foreach ($fresh['rows'] as $row) {
                $exists = BulkEmailLog::where('mail_type', 'camp_invitation')->where('related_type', CampNominee::class)
                    ->where('related_id', $row['nominee_id'])->where('recipient_email', $row['email'])
                    ->where(fn ($q) => $q->whereIn('status', ['queued', 'sending', 'sent', 'acceptance_unknown'])->orWhereNotNull('sent_at'))->exists();
                if ($exists) continue;
                $payload = ['event_id' => $plan->event_id, 'related_type' => CampNominee::class, 'related_id' => $row['nominee_id'],
                    'recipient_email' => $row['email'], 'recipient_name' => $row['name'], 'notification_only' => $row['notification_only'],
                    'contacts' => $row['contacts'], 'primary' => $row['primary'], 'configuration_revision' => $row['configuration_revision'],
                    'rendered_html' => $row['html'], 'rendered_subject' => $row['subject'], 'event_hash' => $row['event_hash'],
                    ...$fresh['envelope']];
                $payload['payload_integrity'] = app(InvitationMailSecurity::class)->payloadIntegrity($payload);
                $log = BulkEmailLog::create(['mail_type' => 'camp_invitation', 'related_type' => CampNominee::class, 'related_id' => $row['nominee_id'],
                    'recipient_email' => $row['email'], 'recipient_name' => $row['name'], 'payload' => $payload, 'status' => 'queued', 'queued_at' => now()]);
                SendCampInvitation::dispatch($log->id, $plan->event_id)->afterCommit();
            }
            $review->update(['queued_at' => now()]);
        });
    }
}
