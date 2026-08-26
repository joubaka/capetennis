<?php

declare(strict_types=1);

namespace App\Services\Masters;

use App\Models\CategoryEvent;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrderItems;
use App\Models\RegistrationOrder;
use App\Models\SeriesRanking;
use App\Models\User;
use App\Models\Event;
use App\Models\Category;
use App\Models\MastersRankingCategoryLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

final class MastersInvitationService
{
    public function syncRankingCategories(Event $event): array
    {
        Log::info('Masters ranking category sync started', [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'event_type' => $event->eventType,
            'series_id' => $event->series_id,
        ]);

        if (!$event->series_id || !$event->series) {
            Log::warning('Masters ranking category sync blocked: event has no linked series', [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'series_id' => $event->series_id,
            ]);
            return [];
        }

        $lists = $event->series->ranking_lists()->with('category')->get();
        Log::info('Masters ranking category sync loaded ranking lists', [
            'event_id' => $event->id,
            'series_id' => $event->series_id,
            'ranking_list_count' => $lists->count(),
            'ranking_list_ids' => $lists->pluck('id')->values()->all(),
        ]);

        $synced = DB::transaction(function () use ($event, $lists) {
            return $lists->map(function ($list) use ($event) {
                $name = trim((string) ($list->category?->name ?? 'Ranking list '.$list->id)).' Masters';
                Log::info('Masters ranking category sync processing list', [
                    'event_id' => $event->id,
                    'series_id' => $event->series_id,
                    'ranking_list_id' => $list->id,
                    'ranking_list_name' => $list->name ?? null,
                    'category_id' => $list->category_id ?? null,
                    'category_name' => $list->category?->name,
                    'target_category_name' => $name,
                ]);
                $category = Category::firstOrCreate(['name' => $name]);
                $categoryEvent = CategoryEvent::firstOrCreate(
                    ['event_id' => $event->id, 'category_id' => $category->id],
                    ['entry_fee' => $event->entryFee, 'ordering' => $list->id]
                );
                $link = MastersRankingCategoryLink::updateOrCreate(
                    ['event_id' => $event->id, 'ranking_list_id' => $list->id],
                    ['category_event_id' => $categoryEvent->id, 'category_name' => $name]
                )->load(['rankingList.category', 'categoryEvent.category']);
                Log::info('Masters ranking category sync list completed', [
                    'event_id' => $event->id,
                    'ranking_list_id' => $list->id,
                    'category_id' => $category->id,
                    'category_event_id' => $categoryEvent->id,
                    'link_id' => $link->id,
                ]);
                return $link;
            })->all();
        });

        Log::info('Masters ranking category sync completed', [
            'event_id' => $event->id,
            'series_id' => $event->series_id,
            'synced_count' => count($synced),
        ]);

        return $synced;
    }

    public function updateRankingCategoryLinks(Event $event, array $links): void
    {
        DB::transaction(function () use ($event, $links) {
            foreach ($links as $linkId => $data) {
                $link = MastersRankingCategoryLink::where('event_id', $event->id)->findOrFail($linkId);
                if ($link->enabled && !($data['enabled'] ?? false) && MastersInvitationBatch::where('event_id', $event->id)->exists()) {
                    throw ValidationException::withMessages(['links' => 'A ranking list cannot be disabled after an invitation batch exists.']);
                }
                $link->update(['enabled' => (bool) ($data['enabled'] ?? false), 'top_x' => max(1, min(100, (int) ($data['top_x'] ?? 8)))]);
            }
        });
    }

    public function updateRankingCategoryLink(Event $event, MastersRankingCategoryLink $link, ?bool $enabled = null, ?int $topX = null): MastersRankingCategoryLink
    {
        abort_unless((int) $link->event_id === (int) $event->id, 404);

        if ($enabled === false && $link->enabled && MastersInvitationBatch::where('event_id', $event->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'A ranking list cannot be disabled after an invitation batch exists.']);
        }

        $values = [];
        if ($enabled !== null) $values['enabled'] = $enabled;
        if ($topX !== null) $values['top_x'] = max(1, min(100, $topX));
        if ($values) $link->update($values);

        return $link->fresh(['rankingList.category', 'categoryEvent.category']);
    }

    public function removeRankingListFromBatch(MastersInvitationBatch $batch, int $rankingListId, User $actor): int
    {
        return DB::transaction(function () use ($batch, $rankingListId, $actor) {
            $batch = MastersInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'sent') {
                throw ValidationException::withMessages(['ranking_list' => 'A sent invitation batch cannot be changed. Manage withdrawals and replacements from the live dashboard.']);
            }
            $invitations = $batch->invitations()->where('ranking_list_id', $rankingListId)->lockForUpdate()->get();
            if ($invitations->isEmpty()) {
                throw ValidationException::withMessages(['ranking_list' => 'That ranking list is not part of this invitation batch.']);
            }
            if ($invitations->contains(fn (MastersInvitation $invitation) => $invitation->order_id || $invitation->registration_id || in_array($invitation->status, [MastersInvitation::ACCEPTED_PENDING_PAYMENT, MastersInvitation::PAID_CONFIRMED], true))) {
                throw ValidationException::withMessages(['ranking_list' => 'This ranking list cannot be removed because a player has started or completed payment.']);
            }

            $count = $invitations->count();
            MastersInvitation::whereIn('id', $invitations->pluck('id'))->delete();
            activity('masters')->performedOn($batch)->causedBy($actor)
                ->withProperties(['ranking_list_id' => $rankingListId, 'removed_invitations' => $count])
                ->log('Masters ranking list removed from invitation batch for restart');
            return $count;
        });
    }

    public function restartBatch(MastersInvitationBatch $batch, User $actor): void
    {
        DB::transaction(function () use ($batch, $actor) {
            $batch = MastersInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === 'sent') {
                throw ValidationException::withMessages(['batch' => 'A sent invitation batch cannot be restarted. Manage withdrawals and replacements from the live dashboard.']);
            }
            $invitations = $batch->invitations()->lockForUpdate()->get();
            if ($invitations->contains(fn (MastersInvitation $invitation) => $invitation->order_id || $invitation->registration_id || in_array($invitation->status, [MastersInvitation::ACCEPTED_PENDING_PAYMENT, MastersInvitation::PAID_CONFIRMED], true))) {
                throw ValidationException::withMessages(['batch' => 'This batch cannot be restarted because at least one player has started or completed payment.']);
            }

            activity('masters')->performedOn($batch)->causedBy($actor)
                ->withProperties(['removed_invitations' => $invitations->count(), 'ranking_run_id' => $batch->ranking_run_id])
                ->log('Masters invitation batch restarted');
            $batch->invitations()->delete();
            $batch->update(['status' => 'restarted', 'auto_replacement_enabled' => false]);
        });
    }

    public function generateBatch(Event $event, int $seriesId, string $runId, array $mappings, int $topX, User $actor, array $options = []): MastersInvitationBatch
    {
        if (!$event->series_id || (int) $event->series_id !== $seriesId) {
            throw ValidationException::withMessages(['series' => 'The Masters event is not linked to the selected source series.']);
        }
        $rows = SeriesRanking::query()->where('series_id', $seriesId)->where('run_id', $runId)
            ->where('status', 'published')->orderBy('ranking_list_id')->orderBy('rank_position')->get();
        if ($rows->isEmpty()) throw ValidationException::withMessages(['ranking' => 'No published ranking rows were found for this run.']);
        return DB::transaction(function () use ($event, $seriesId, $runId, $mappings, $topX, $actor, $options, $rows) {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            if (MastersInvitationBatch::where('event_id', $event->id)->whereIn('status', ['generated', 'ready_for_invitation', 'sent'])->exists()) {
                throw ValidationException::withMessages(['batch' => 'This Masters event already has an active invitation batch. Complete or restart it before generating another batch.']);
            }
            $batch = MastersInvitationBatch::create([
                'event_id' => $event->id, 'series_id' => $seriesId, 'ranking_run_id' => $runId,
                'created_by' => $actor->id, 'top_x' => $topX,
                'auto_replacement_enabled' => false, 'response_deadline' => $options['response_deadline'] ?? null,
                'payment_deadline' => $options['payment_deadline'] ?? null,
                'replacement_payment_deadline' => $options['replacement_payment_deadline'] ?? null,
                'status' => 'generated',
            ]);
            $usedPlayers = [];
            foreach ($mappings as $mapping) {
                $rankingListId = (int) ($mapping['ranking_list_id'] ?? 0);
                $categoryEventId = (int) ($mapping['category_event_id'] ?? 0);
                if (!$rankingListId || !$categoryEventId || !CategoryEvent::where('id', $categoryEventId)->where('event_id', $event->id)->exists()) {
                    throw ValidationException::withMessages(['mapping' => 'Every ranking list must map to a category in this event.']);
                }
                $link = MastersRankingCategoryLink::where('event_id', $event->id)
                    ->where('ranking_list_id', $rankingListId)
                    ->where('category_event_id', $categoryEventId)
                    ->first();
                if (!$link || !$link->enabled) {
                    throw ValidationException::withMessages(['mapping' => 'A disabled ranking list cannot be included in an invitation batch. Refresh the page and select only enabled categories.']);
                }
                $queue = $rows->where('ranking_list_id', $rankingListId)->values();
                $mappingTopX = (int) ($link->top_x ?: $topX);
                foreach ($queue as $index => $row) {
                    if (isset($usedPlayers[$row->player_id])) continue;
                    $usedPlayers[$row->player_id] = true;
                    $player = Player::find($row->player_id);
                    MastersInvitation::create([
                        'batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $categoryEventId,
                        'ranking_list_id' => $rankingListId, 'ranking_category_id' => $row->category_id,
                        'player_id' => $row->player_id, 'ranking_position' => $row->rank_position,
                        'queue_position' => $index + 1, 'total_points' => $row->total_points,
                        'status' => $index < $mappingTopX ? MastersInvitation::INVITED : MastersInvitation::RESERVE,
                        'invited_at' => $index < $mappingTopX ? now() : null,
                        'snapshot_json' => ['player_name' => $player?->full_name, 'rank_position' => $row->rank_position, 'total_points' => $row->total_points],
                    ]);
                }
            }
            return $batch;
        });
    }

    public function updateBatchDetails(MastersInvitationBatch $batch, array $details): MastersInvitationBatch
    {
        $response = isset($details['response_deadline']) ? now()->parse($details['response_deadline']) : null;
        $payment = isset($details['payment_deadline']) ? now()->parse($details['payment_deadline']) : null;
        $replacement = isset($details['replacement_payment_deadline']) ? now()->parse($details['replacement_payment_deadline']) : null;
        if (!$response || !$payment || !$replacement) {
            throw ValidationException::withMessages(['deadlines' => 'All Masters deadlines are required before invitations can be sent.']);
        }
        if ($response->isPast() || $payment->isPast() || $replacement->isPast()) {
            throw ValidationException::withMessages(['deadlines' => 'Masters deadlines must be in the future.']);
        }
        if ($payment->lt($response)) {
            throw ValidationException::withMessages(['payment_deadline' => 'The payment deadline must be after the response deadline.']);
        }
        if ($replacement->lt($payment)) {
            throw ValidationException::withMessages(['replacement_payment_deadline' => 'The replacement payment deadline must be on or after the payment deadline.']);
        }
        $batch->update([
            'response_deadline' => $response,
            'payment_deadline' => $payment,
            'replacement_payment_deadline' => $replacement,
            'status' => 'ready_for_invitation',
        ]);

        return $batch->fresh();
    }

    public function resetCancelledPayment(RegistrationOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $invitation = MastersInvitation::query()->lockForUpdate()
                ->where('order_id', $order->id)
                ->where('status', MastersInvitation::ACCEPTED_PENDING_PAYMENT)
                ->first();
            if (!$invitation) return;
            $invitation->update(['status' => MastersInvitation::INVITED, 'order_id' => null, 'registration_id' => null, 'accepted_at' => null]);
            $this->recordActor($invitation, $actor, 'cancelled PayFast payment and returned invitation to register');
        });
    }

    public function sendInvitations(MastersInvitationBatch $batch): int
    {
        if (!$batch->response_deadline || !$batch->payment_deadline || !$batch->replacement_payment_deadline) {
            throw ValidationException::withMessages(['batch' => 'Save all invitation deadlines before sending invitations.']);
        }
        if ($batch->status === 'sent') {
            throw ValidationException::withMessages(['batch' => 'Invitations have already been sent for this batch.']);
        }
        return DB::transaction(function () use ($batch) {
            $lockedBatch = MastersInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($lockedBatch->status === 'sent') {
                throw ValidationException::withMessages(['batch' => 'Invitations have already been sent for this batch.']);
            }
            $invitations = $lockedBatch->invitations()
                ->where('status', MastersInvitation::INVITED)
                ->lockForUpdate()->get();
            if ($invitations->isEmpty()) {
                throw ValidationException::withMessages(['batch' => 'There are no invitees selected to receive invitations.']);
            }
            foreach ($invitations as $invitation) {
                $this->queuePlayerMail($invitation, 'invitation');
            }
            $lockedBatch->update(['status' => 'sent']);
            return $invitations->count();
        });
    }

    public function setPublicListPublished(MastersInvitationBatch $batch, bool $published, User $actor): MastersInvitationBatch
    {
        if ($published && $batch->status !== 'sent') {
            throw ValidationException::withMessages(['batch' => 'Send the invitations before publishing the Masters player list.']);
        }
        $batch->update(['public_list_published' => $published]);
        activity('masters')->performedOn($batch)->causedBy($actor)
            ->withProperties(['published' => $published])
            ->log($published ? 'Masters invitation list published publicly' : 'Masters invitation list unpublished publicly');
        return $batch->fresh();
    }

    public function setRegistrationOpen(MastersInvitationBatch $batch, bool $open, User $actor): MastersInvitationBatch
    {
        if ($open && $batch->status !== 'sent') {
            throw ValidationException::withMessages(['batch' => 'Send invitations before opening Masters registration.']);
        }
        if ($open && !$batch->public_list_published) {
            throw ValidationException::withMessages(['batch' => 'Publish the public player list before opening Masters registration.']);
        }

        $batch->update(['registration_open' => $open]);
        activity('masters')->performedOn($batch)->causedBy($actor)
            ->withProperties(['registration_open' => $open])
            ->log($open ? 'Masters registration opened' : 'Masters registration closed');

        return $batch->fresh();
    }

    public function updateInvitationWave(MastersInvitation $invitation, string $status, User $actor): MastersInvitation
    {
        if (!in_array($status, [MastersInvitation::INVITED, MastersInvitation::RESERVE], true)) {
            throw ValidationException::withMessages(['invitation' => 'Only invited and reserve statuses can be adjusted here.']);
        }

        $oldStatus = $invitation->status;
        $sentBatchPromotion = $invitation->batch?->status === 'sent'
            && $oldStatus === MastersInvitation::RESERVE
            && $status === MastersInvitation::INVITED;
        if ($invitation->batch?->status === 'sent' && !$sentBatchPromotion) {
            throw ValidationException::withMessages(['invitation' => 'After invitations are sent, only a reserve player can be promoted as a replacement.']);
        }
        $invitation->update([
            'status' => $status,
            'invited_at' => $status === MastersInvitation::INVITED ? ($invitation->invited_at ?? now()) : null,
        ]);
        activity('masters')->performedOn($invitation->batch)->causedBy($actor)
            ->withProperties(['invitation_id' => $invitation->id, 'player_id' => $invitation->player_id, 'from' => $oldStatus, 'to' => $status])
            ->log($sentBatchPromotion ? 'Masters reserve player manually promoted as replacement' : ($status === MastersInvitation::INVITED ? 'Masters player added to invitation wave' : 'Masters player removed from invitation wave'));
        $fresh = $invitation->fresh(['player', 'categoryEvent.category', 'batch.event']);
        if ($sentBatchPromotion) {
            $this->queuePlayerMail($fresh, 'invitation');
            $this->queueAdminMail($fresh, 'replacement');
        }
        return $fresh;
    }

    public function queuePlayerMail(MastersInvitation $invitation, string $kind = 'invitation'): void
    {
        $invitation->loadMissing(['player.user', 'player.users', 'batch.event', 'categoryEvent.category']);
        $user = $this->playerUser($invitation->player);
        if ($user?->email) Mail::to($user->email)->queue(new \App\Mail\MastersInvitationMail($invitation, $kind));
    }

    public function queueAdminMail(MastersInvitation $invitation, string $action, ?MastersInvitation $replacement = null): void
    {
        $invitation->loadMissing(['batch.event.admins', 'categoryEvent.category', 'player']);
        foreach ($invitation->batch?->event?->admins ?? [] as $admin) {
            if ($admin->email) Mail::to($admin->email)->queue(new \App\Mail\MastersAdminUpdateMail($invitation, $action, $replacement));
        }
    }

    public function readiness(MastersInvitationBatch $batch): array
    {
        $groups = $batch->invitations()->with(['player', 'categoryEvent.category'])
            ->get()->groupBy('category_event_id');
        $results = [];

        foreach ($groups as $categoryEventId => $invitations) {
            $reserve = $invitations->where('status', MastersInvitation::RESERVE);
            $category = $invitations->first()?->categoryEvent;
            $blocking = [];
            $warnings = [];
            if (!$category) $blocking[] = 'Target age group mapping is missing.';
            if ($invitations->isEmpty()) $blocking[] = 'No ranked players found in this age group.';
            if ($reserve->isEmpty()) $warnings[] = 'No reserve players remain for automatic replacement.';
            foreach ($invitations as $invitation) {
                $playerLabel = $invitation->player?->full_name ?: trim(($invitation->player?->name ?? '') . ' ' . ($invitation->player?->surname ?? '')) ?: "Player {$invitation->player_id}";
                if (!$invitation->player?->isProfileComplete()) {
                    $warnings[] = "{$playerLabel} has an incomplete profile; complete it at the next registration.";
                }
                if (!$this->playerUser($invitation->player)) {
                    $warnings[] = "{$playerLabel} has no linked login account; confirm the profile at the next registration.";
                }
            }
            $results[$categoryEventId] = [
                'category_event_id' => (int) $categoryEventId,
                'label' => $category?->category?->name,
                'status' => $blocking ? 'blocked' : ($warnings ? 'warning' : 'ready'),
                'blocking' => array_values(array_unique($blocking)),
                'warnings' => array_values(array_unique($warnings)),
                'candidate_count' => $invitations->count(),
                'reserve_count' => $reserve->count(),
            ];
        }

        return [
            'status' => collect($results)->contains(fn ($r) => $r['status'] === 'blocked') ? 'blocked'
                : (collect($results)->contains(fn ($r) => $r['status'] === 'warning') ? 'warning' : 'ready'),
            'groups' => array_values($results),
        ];
    }

    public function accept(MastersInvitation $invitation, User $user): RegistrationOrder
    {
        return DB::transaction(function () use ($invitation, $user) {
            $locked = MastersInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $this->recordActor($locked, $user, 'accepted invitation and started PayFast registration');
            if ($locked->status === MastersInvitation::ACCEPTED_PENDING_PAYMENT && $locked->order_id) {
                return RegistrationOrder::findOrFail($locked->order_id);
            }
            $now = now();
            $paymentDeadline = $locked->promoted_from_id
                ? $locked->batch->replacement_payment_deadline
                : $locked->batch->payment_deadline;
            $responseDeadline = $locked->promoted_from_id
                ? $locked->batch->replacement_payment_deadline
                : $locked->batch->response_deadline;
            if (!$locked->batch->registration_open) {
                throw ValidationException::withMessages(['invitation' => 'Masters registration is currently closed.']);
            }
            if ($locked->status !== MastersInvitation::INVITED
                || ($responseDeadline && $now->gt($responseDeadline))
                || ($paymentDeadline && $now->gt($paymentDeadline))) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
            }

            $registration = Registration::create([]);
            $registration->players()->sync([$locked->player_id]);
            $registration->categoryEvents()->sync([$locked->category_event_id => [
                'payment_status_id' => 0, 'user_id' => $user->id, 'status' => 'active',
            ]]);

            $fee = (float) ($locked->categoryEvent?->entry_fee ?? 0);
            $order = RegistrationOrder::create([
                'user_id' => $user->id, 'payfast_amount_due' => $fee, 'total_fee' => $fee,
                'wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_paid' => false,
                'pay_status' => false, 'payment_method' => 'payfast',
            ]);
            $item = new RegistrationOrderItems();
            $item->order_id = $order->id;
            $item->category_event_id = $locked->category_event_id;
            $item->registration_id = $registration->id;
            $item->player_id = $locked->player_id;
            $item->user_id = $user->id;
            $item->item_price = $fee;
            $item->save();

            $locked->update(['registration_id' => $registration->id, 'order_id' => $order->id,
                'status' => MastersInvitation::ACCEPTED_PENDING_PAYMENT, 'accepted_at' => now()]);
            return $order;
        });
    }

    public function decline(MastersInvitation $invitation, User $user, ?string $reason = null): ?MastersInvitation
    {
        return DB::transaction(function () use ($invitation, $user, $reason) {
            $locked = MastersInvitation::query()->lockForUpdate()->with('batch')->findOrFail($invitation->id);
            $this->recordActor($locked, $user, 'declined invitation');
            if ($locked->status !== MastersInvitation::INVITED
                || ($locked->batch->response_deadline && now()->gt($locked->batch->response_deadline))) {
                throw ValidationException::withMessages(['invitation' => 'This invitation cannot be declined.']);
            }
            $locked->update(['status' => MastersInvitation::DECLINED, 'declined_at' => now(),
                'decline_confirmation_sent_at' => now(), 'decline_confirmed_at' => null,
                'decline_reason' => Str::limit($reason, 1000)]);
            DB::afterCommit(function () use ($locked) {
                $fresh = $locked->fresh();
                $this->queuePlayerMail($fresh, 'declined');
                $this->queueAdminMail($fresh, 'declined');
            });
            return null;
        });
    }

    public function confirmDecline(MastersInvitation $invitation): ?MastersInvitation
    {
        return DB::transaction(function () use ($invitation) {
            $locked = MastersInvitation::query()->lockForUpdate()->with('batch')->findOrFail($invitation->id);
            if ($locked->status !== MastersInvitation::DECLINED) {
                return null;
            }
            if ($locked->decline_confirmed_at) {
                return null;
            }
            $locked->update(['decline_confirmed_at' => now()]);
            activity('masters')->performedOn($locked->batch)
                ->withProperties(['invitation_id' => $locked->id, 'player_id' => $locked->player_id])
                ->log('Masters player confirmed invitation decline by email');
            return $this->replacementAfter($locked);
        });
    }

    public function replacementAfter(MastersInvitation $vacancy): ?MastersInvitation
    {
        $batch = MastersInvitationBatch::query()->lockForUpdate()->findOrFail($vacancy->batch_id);
        if (!$batch->auto_replacement_enabled) return null;
        if ($batch->replacement_payment_deadline && now()->gt($batch->replacement_payment_deadline)) return null;
        $check = $this->readiness($batch);
        $group = collect($check['groups'])->firstWhere('category_event_id', $vacancy->category_event_id);
        if (($group['status'] ?? 'blocked') === 'blocked') return null;

        $candidate = MastersInvitation::query()->lockForUpdate()
            ->where('batch_id', $batch->id)->where('category_event_id', $vacancy->category_event_id)
            ->where('status', MastersInvitation::RESERVE)->orderBy('queue_position')->first();
        if (!$candidate) return null;
        $candidate->update(['status' => MastersInvitation::INVITED, 'invited_at' => now(),
            'promoted_from_id' => $vacancy->id, 'replacement_sent_at' => now()]);
        $replacement = $candidate->fresh(['player', 'categoryEvent', 'batch']);
        DB::afterCommit(function () use ($replacement, $vacancy) {
            $this->queuePlayerMail($replacement, 'replacement');
            $this->queueAdminMail($vacancy->fresh(), 'replacement_sent', $replacement);
        });
        return $replacement;
    }

    public function confirmPaidOrder(RegistrationOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $invitation = MastersInvitation::query()->lockForUpdate()->where('order_id', $order->id)->first();
            if (!$invitation || $invitation->status === MastersInvitation::PAID_CONFIRMED) return;
            if ($invitation->status !== MastersInvitation::ACCEPTED_PENDING_PAYMENT) {
                throw ValidationException::withMessages(['payment' => 'Masters invitation is not awaiting payment.']);
            }
            $invitation->update(['status' => MastersInvitation::PAID_CONFIRMED, 'paid_at' => now()]);
            DB::afterCommit(fn () => $this->queuePlayerMail($invitation->fresh(), 'confirmed'));
        });
    }

    public function handlePaidWithdrawal(int $registrationId, ?User $actor = null): ?MastersInvitation
    {
        return DB::transaction(function () use ($registrationId, $actor) {
            $invitation = MastersInvitation::query()->lockForUpdate()
                ->where('registration_id', $registrationId)->where('status', MastersInvitation::PAID_CONFIRMED)->first();
            if (!$invitation) return null;
            $invitation->update(['status' => MastersInvitation::WITHDRAWN, 'withdrawn_at' => now()]);
            if ($actor) $this->recordActor($invitation, $actor, 'withdrew Masters registration');
            DB::afterCommit(function () use ($invitation) {
                $fresh = $invitation->fresh(['player', 'batch.event', 'categoryEvent.category']);
                $this->queuePlayerMail($fresh, 'withdrawn');
                $this->queueAdminMail($fresh, 'withdrawn');
            });
            return $this->replacementAfter($invitation);
        });
    }

    private function recordActor(MastersInvitation $invitation, User $user, string $action): void
    {
        activity('masters')->performedOn($invitation)->causedBy($user)
            ->withProperties([
                'invitation_id' => $invitation->id,
                'player_id' => $invitation->player_id,
                'category_event_id' => $invitation->category_event_id,
                'acting_user_id' => $user->id,
                'acting_user_email' => $user->email,
                'player_account_id' => $this->playerUser($invitation->player)?->id,
            ])->log($action);
    }

    private function playerUser(?Player $player): ?User
    {
        if (!$player) return null;
        return $player->user ?: $player->users()->first();
    }
}
