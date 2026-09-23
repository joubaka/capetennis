<?php

declare(strict_types=1);

namespace App\Services\Masters;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Domain\Ranking\Services\RankingTeamEligibilityService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrderItems;
use App\Models\RegistrationOrder;
use App\Models\SeriesRanking;
use App\Models\Series;
use App\Models\User;
use App\Models\Event;
use App\Models\Category;
use App\Models\MastersRankingCategoryLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\BulkEmailLog;
use App\Jobs\SendMastersInvitationEmailJob;
use App\Domain\Entries\Services\EntryService;

final class MastersInvitationService
{
    public function __construct(
        private readonly RankingTeamEligibilityService $rankingTeamEligibility,
    ) {}

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
        $series = $event->series()->firstOrFail();
        return DB::transaction(function () use ($event, $series, $seriesId, $runId, $mappings, $topX, $actor, $options, $rows) {
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
                $allRankedPlayers = $rows->where('ranking_list_id', $rankingListId)->values();
                $queue = $this->rankingTeamEligibility->eligible($allRankedPlayers, $series);
                if ($queue->isEmpty()) {
                    throw ValidationException::withMessages([
                        'ranking' => 'No team-eligible players were found for one of the selected ranking lists.',
                    ]);
                }
                $mappingTopX = (int) ($link->top_x ?: $topX);
                foreach ($queue as $index => $row) {
                    if (isset($usedPlayers[$row->player_id])) continue;
                    $usedPlayers[$row->player_id] = true;
                    $player = Player::find($row->player_id);
                    $eligibility = $this->rankingTeamEligibility->assess($row, $series);
                    MastersInvitation::create([
                        'batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $categoryEventId,
                        'ranking_list_id' => $rankingListId, 'ranking_category_id' => $row->category_id,
                        'player_id' => $row->player_id, 'ranking_position' => $row->rank_position,
                        'queue_position' => $index + 1, 'total_points' => $row->total_points,
                        'status' => $index < $mappingTopX ? MastersInvitation::INVITED : MastersInvitation::RESERVE,
                        'invited_at' => $index < $mappingTopX ? now() : null,
                        'snapshot_json' => [
                            'player_name' => $player?->full_name,
                            'rank_position' => $row->rank_position,
                            'total_points' => $row->total_points,
                            'events_played' => $eligibility['events_played'],
                            'minimum_events_for_team_selection' => $eligibility['minimum_events'],
                        ],
                    ]);
                }
            }
            return $batch;
        });
    }

    public function updateBatchDetails(MastersInvitationBatch $batch, array $details): MastersInvitationBatch
    {
        return DB::transaction(function () use ($batch, $details) {
            $locked = MastersInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === 'sent') {
                throw ValidationException::withMessages([
                    'deadlines' => 'Sent invitation deadlines must be changed with the deadline extension action.',
                ]);
            }

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
            $locked->update([
                'response_deadline' => $response,
                'payment_deadline' => $payment,
                'replacement_payment_deadline' => $replacement,
                'status' => 'ready_for_invitation',
            ]);

            return $locked->fresh();
        });
    }

    public function extendBatchDeadlines(MastersInvitationBatch $batch, array $details, User $actor): MastersInvitationBatch
    {
        return DB::transaction(function () use ($batch, $details, $actor) {
            $locked = MastersInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'sent') {
                throw ValidationException::withMessages([
                    'deadlines' => 'Only a sent invitation batch can use the deadline extension action.',
                ]);
            }

            $response = isset($details['response_deadline']) ? now()->parse($details['response_deadline']) : null;
            $payment = isset($details['payment_deadline']) ? now()->parse($details['payment_deadline']) : null;
            $replacement = isset($details['replacement_payment_deadline']) ? now()->parse($details['replacement_payment_deadline']) : null;
            if (!$response || !$payment || !$replacement) {
                throw ValidationException::withMessages(['deadlines' => 'All Masters deadlines are required.']);
            }
            if (!$locked->response_deadline || !$locked->payment_deadline || !$locked->replacement_payment_deadline) {
                throw ValidationException::withMessages(['deadlines' => 'The current invitation deadlines are incomplete and cannot be extended safely.']);
            }
            if ($response->isPast() || $payment->isPast() || $replacement->isPast()) {
                throw ValidationException::withMessages(['deadlines' => 'Extended Masters deadlines must be in the future.']);
            }
            if ($response->lt($locked->response_deadline)
                || $payment->lt($locked->payment_deadline)
                || $replacement->lt($locked->replacement_payment_deadline)) {
                throw ValidationException::withMessages(['deadlines' => 'Invitation deadlines may only be moved later, not shortened.']);
            }
            if ($payment->lt($response)) {
                throw ValidationException::withMessages(['payment_deadline' => 'The payment deadline must be on or after the response deadline.']);
            }
            if ($replacement->lt($payment)) {
                throw ValidationException::withMessages(['replacement_payment_deadline' => 'The replacement payment deadline must be on or after the payment deadline.']);
            }
            if ($response->equalTo($locked->response_deadline)
                && $payment->equalTo($locked->payment_deadline)
                && $replacement->equalTo($locked->replacement_payment_deadline)) {
                throw ValidationException::withMessages(['deadlines' => 'Move at least one invitation deadline to a later date or time.']);
            }

            $before = [
                'response_deadline' => $locked->response_deadline->toIso8601String(),
                'payment_deadline' => $locked->payment_deadline->toIso8601String(),
                'replacement_payment_deadline' => $locked->replacement_payment_deadline->toIso8601String(),
            ];
            $locked->update([
                'response_deadline' => $response,
                'payment_deadline' => $payment,
                'replacement_payment_deadline' => $replacement,
            ]);
            activity('masters')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'before' => $before,
                    'after' => [
                        'response_deadline' => $response->toIso8601String(),
                        'payment_deadline' => $payment->toIso8601String(),
                        'replacement_payment_deadline' => $replacement->toIso8601String(),
                    ],
                ])->log('Masters invitation deadlines extended');

            return $locked->fresh();
        });
    }

    public function resetCancelledPayment(RegistrationOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor) {
            $invitation = MastersInvitation::query()->lockForUpdate()
                ->where('order_id', $order->id)
                ->where('status', MastersInvitation::ACCEPTED_PENDING_PAYMENT)
                ->first();
            if (!$invitation) return;
            $this->softDeleteUnpaidDraftEntry($invitation->registration_id, $invitation->category_event_id);
            $invitation->update(['status' => MastersInvitation::INVITED, 'order_id' => null, 'registration_id' => null, 'accepted_at' => null]);
            $this->recordActor($invitation, $actor, 'cancelled PayFast payment and returned invitation to register');
        });
    }

    public function sendInvitations(MastersInvitationBatch $batch): array
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
            $report = ['queued' => 0, 'skipped' => 0, 'failed' => 0, 'details' => []];
            Log::info('Masters invitation batch send started', ['batch_id' => $lockedBatch->id, 'event_id' => $lockedBatch->event_id, 'selected_count' => $invitations->count()]);
            foreach ($invitations as $invitation) {
                $invitation->loadMissing(['player', 'player.user', 'player.users', 'categoryEvent.category']);
                $recipient = $this->playerUser($invitation->player);
                $email = $recipient?->email;
                $context = ['batch_id' => $lockedBatch->id, 'invitation_id' => $invitation->id, 'player_id' => $invitation->player_id, 'player' => $invitation->player?->full_name, 'category' => $invitation->categoryEvent?->category?->name, 'recipient' => $email];
                if (!$email) {
                    $report['skipped']++;
                    $report['details'][] = array_merge($context, ['result' => 'skipped', 'error' => 'No linked player account email address.']);
                    Log::warning('Masters invitation email skipped', $report['details'][array_key_last($report['details'])]);
                    continue;
                }
                try {
                    $log = BulkEmailLog::create(['mail_type' => 'masters_invitation', 'related_type' => MastersInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => $email, 'recipient_name' => $invitation->player?->full_name, 'status' => 'queued', 'payload' => ['invitation_id' => $invitation->id, 'kind' => 'invitation'], 'queued_at' => now()]);
                    SendMastersInvitationEmailJob::dispatch($log->id, $lockedBatch->event_id);
                    $report['queued']++;
                    $report['details'][] = array_merge($context, ['result' => 'queued']);
                    Log::info('Masters invitation email queued', $report['details'][array_key_last($report['details'])]);
                } catch (\Throwable $e) {
                    $report['failed']++;
                    $report['details'][] = array_merge($context, ['result' => 'failed', 'error' => $e->getMessage()]);
                    Log::error('Masters invitation email queue failed', array_merge($context, ['error' => $e->getMessage()]));
                }
            }
            $lockedBatch->update(['status' => 'sent', 'public_list_published' => true]);
            activity('masters')->performedOn($lockedBatch)->causedBy(auth()->user())
                ->withProperties(['published' => true, 'emails_sent' => true, 'invitation_count' => $invitations->count()])
                ->log('Masters player names published automatically when invitations were sent');
            Log::info('Masters invitation batch send completed', ['batch_id' => $lockedBatch->id, 'event_id' => $lockedBatch->event_id, 'selected' => $invitations->count(), 'queued' => $report['queued'], 'skipped' => $report['skipped'], 'failed' => $report['failed'], 'batch_status' => 'sent', 'public_list_published' => true]);
            return $report;
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

    public function publishNamesOnly(MastersInvitationBatch $batch, User $actor): MastersInvitationBatch
    {
        if ($batch->status === 'sent') {
            throw ValidationException::withMessages(['batch' => 'Use the existing public player list control for an invitation batch that has already been sent.']);
        }

        $batch->update(['public_list_published' => true]);
        activity('masters')->performedOn($batch)->causedBy($actor)
            ->withProperties(['published' => true, 'emails_sent' => false])
            ->log('Masters player names published publicly without sending invitations');

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

    public function removeByAdmin(MastersInvitation $invitation, User $actor): MastersInvitation
    {
        return DB::transaction(function () use ($invitation, $actor) {
            $locked = MastersInvitation::query()->lockForUpdate()->with('batch')->findOrFail($invitation->id);
            if ($locked->batch?->status === 'sent') {
                throw ValidationException::withMessages(['invitation' => 'Sent invitations cannot be removed. Manage withdrawals and replacements from the live dashboard.']);
            }
            if ($locked->order_id || $locked->registration_id || in_array($locked->status, [MastersInvitation::ACCEPTED_PENDING_PAYMENT, MastersInvitation::PAID_CONFIRMED], true)) {
                throw ValidationException::withMessages(['invitation' => 'A player who has started or completed payment cannot be removed by admin.']);
            }
            if (!in_array($locked->status, [MastersInvitation::INVITED, MastersInvitation::RESERVE], true)) {
                throw ValidationException::withMessages(['invitation' => 'This player is no longer available for admin removal.']);
            }
            $locked->update(['status' => MastersInvitation::ADMIN_REMOVED, 'declined_at' => now(), 'decline_reason' => 'Removed by admin', 'decline_method' => 'admin_dashboard', 'admin_removed_by_user_id' => $actor->id, 'admin_removed_at' => now(), 'invited_at' => null]);
            activity('masters')->performedOn($locked)->causedBy($actor)
                ->withProperties(['invitation_id' => $locked->id, 'player_id' => $locked->player_id])
                ->log('Masters player removed by admin');
            return $locked->fresh(['player', 'categoryEvent.category', 'batch.event']);
        });
    }

    public function restoreToReserve(MastersInvitation $invitation, User $actor): MastersInvitation
    {
        if ($invitation->batch?->status === 'sent' && !in_array($invitation->status, [MastersInvitation::DECLINED, MastersInvitation::ADMIN_REMOVED], true)) {
            throw ValidationException::withMessages(['invitation' => 'A sent invitation cannot be restored directly. Use the live replacement workflow.']);
        }
        if (!in_array($invitation->status, [MastersInvitation::DECLINED, MastersInvitation::ADMIN_REMOVED], true)) {
            throw ValidationException::withMessages(['invitation' => 'Only declined or admin-removed players can be restored.']);
        }
        $oldStatus = $invitation->status;
        $invitation->update(['status' => MastersInvitation::RESERVE]);
        activity('masters')->performedOn($invitation)->causedBy($actor)
            ->withProperties(['invitation_id' => $invitation->id, 'player_id' => $invitation->player_id, 'from' => $oldStatus, 'to' => MastersInvitation::RESERVE])
            ->log('Masters player restored to reserve by admin');
        return $invitation->fresh(['player', 'categoryEvent.category', 'batch.event']);
    }

    public function queuePlayerMail(MastersInvitation $invitation, string $kind = 'invitation'): void
    {
        $invitation->loadMissing(['player.user', 'player.users', 'batch.event', 'categoryEvent.category']);
        $user = $this->playerUser($invitation->player);
        if ($user?->email) {
            $log = BulkEmailLog::create(['mail_type' => 'masters_invitation', 'related_type' => MastersInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => $user->email, 'recipient_name' => $invitation->player?->full_name, 'status' => 'queued', 'payload' => ['invitation_id' => $invitation->id, 'kind' => $kind], 'queued_at' => now()]);
            SendMastersInvitationEmailJob::dispatch($log->id, $invitation->batch->event_id);
        }
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
        if ($paidItem = $this->paidOrderItemFor($invitation)) {
            $this->reconcilePaidInvitation($invitation);

            return RegistrationOrder::findOrFail($paidItem->order_id);
        }

        return DB::transaction(function () use ($invitation, $user) {
            $locked = MastersInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            $this->recordActor($locked, $user, 'started Masters registration and PayFast payment');
            if ($locked->status === MastersInvitation::ACCEPTED_PENDING_PAYMENT && $locked->order_id) {
                return RegistrationOrder::findOrFail($locked->order_id);
            }
            if (!$locked->batch->registration_open && (int) $locked->batch->event?->signUp !== 1) {
                throw ValidationException::withMessages(['invitation' => 'Masters registration is currently closed.']);
            }
            if ($locked->status !== MastersInvitation::INVITED) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
            }

            $registration = Registration::create([]);
            $registration->players()->sync([$locked->player_id]);

            $fee = (float) ($locked->categoryEvent?->entry_fee ?? 0);
            $order = RegistrationOrder::create([
                'user_id' => $user->id, 'payfast_amount_due' => $fee, 'total_fee' => $fee,
                'wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_paid' => false,
                'pay_status' => false, 'payment_method' => 'payfast',
                'status' => 'pending',
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
            $locked->update(['status' => MastersInvitation::DECLINED, 'declined_at' => now(), 'declined_by_user_id' => $user->id,
                'decline_method' => 'authenticated_invitation', 'admin_removed_by_user_id' => null, 'admin_removed_at' => null,
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

    public function createIdentityCorrectionReplacement(
        MastersInvitation $vacancy,
        Player $target,
        User $actor,
        array $correctedRegistrationIds = [18445, 20410],
    ): MastersInvitation {
        if (! $actor->hasRole('super-user')) {
            throw new AuthorizationException('Only a super user may create an identity-correction Masters replacement.');
        }

        return DB::transaction(function () use ($vacancy, $target, $actor, $correctedRegistrationIds): MastersInvitation {
            $batch = MastersInvitationBatch::query()->lockForUpdate()->findOrFail(4);
            $lockedVacancy = MastersInvitation::query()->lockForUpdate()->findOrFail($vacancy->id);
            $lockedTarget = Player::query()->lockForUpdate()->findOrFail($target->id);

            if ((int) $lockedVacancy->id !== 807
                || (int) $lockedVacancy->batch_id !== 4
                || (int) $lockedVacancy->event_id !== 254
                || (int) $lockedVacancy->category_event_id !== 2182
                || (int) $lockedVacancy->ranking_list_id !== 938
                || (int) $lockedVacancy->ranking_category_id !== 131
                || (int) $lockedVacancy->ranking_position !== 8
                || (int) $lockedVacancy->queue_position !== 8
                || (int) $lockedVacancy->total_points !== 80
                || (int) $lockedVacancy->player_id !== 2439
                || $lockedVacancy->status !== MastersInvitation::DECLINED
                || $lockedVacancy->registration_id !== null
                || $lockedVacancy->order_id !== null) {
                throw ValidationException::withMessages(['invitation' => 'The identity-correction vacancy no longer matches the audited declined, unpaid invitation.']);
            }
            if ((int) $batch->event_id !== 254
                || (int) $batch->series_id !== 18
                || $batch->status !== 'sent'
                || ! $batch->public_list_published
                || ! $batch->registration_open
                || ! $batch->replacement_payment_deadline
                || $batch->replacement_payment_deadline->isPast()) {
                throw ValidationException::withMessages(['batch' => 'The Masters batch is not open for this replacement invitation.']);
            }
            if (! CategoryEvent::query()->whereKey(2182)->where('event_id', 254)->where('category_id', 224)->exists()) {
                throw ValidationException::withMessages(['category' => 'The Masters U/9 category mapping no longer matches the audited event.']);
            }
            if ((int) $lockedTarget->id !== 5332
                || strcasecmp(trim((string) $lockedTarget->name), 'Dirkie') !== 0
                || strcasecmp(trim((string) $lockedTarget->surname), 'Coetzee') !== 0
                || (string) $lockedTarget->dateOfBirth !== '2017-09-09'
                || (int) $lockedTarget->userId !== 4025) {
                throw ValidationException::withMessages(['player' => 'The replacement player no longer matches the audited Dirkie Coetzee profile.']);
            }

            $registrationIds = collect($correctedRegistrationIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($registrationIds !== [18445, 20410]
                || DB::table('player_registrations')->whereIn('registration_id', $registrationIds)->count() !== 2
                || DB::table('player_registrations')
                    ->whereIn('registration_id', $registrationIds)
                    ->where('player_id', 5332)
                    ->distinct()
                    ->count('registration_id') !== 2) {
                throw ValidationException::withMessages(['registrations' => 'Both audited Wilson Series registrations must point to Dirkie before the Masters replacement is created.']);
            }

            $targetRanking = SeriesRanking::query()
                ->where('series_id', 18)
                ->where('ranking_list_id', 938)
                ->where('category_id', 131)
                ->where('player_id', 5332)
                ->where('status', 'published')
                ->lockForUpdate()
                ->first();
            if (! $targetRanking
                || (int) $targetRanking->rank_position !== 8
                || (int) $targetRanking->total_points !== 80) {
                throw ValidationException::withMessages(['ranking' => 'Dirkie must have the reviewed and published Wilson Series U/9 ranking before a Masters invitation is created.']);
            }
            $series = Series::query()->lockForUpdate()->find(18);
            $eligibilityEvidence = $this->identityCorrectionEventsPlayed($targetRanking, $registrationIds);
            $eventsPlayed = $eligibilityEvidence['events_played'];
            $minimumEvents = (int) ($series?->minimum_events_for_team_selection ?? 0);
            if ($eventsPlayed !== 2 || $minimumEvents < 1 || $eventsPlayed < $minimumEvents) {
                throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking does not contain the audited two-leg Masters eligibility evidence.']);
            }

            $mastersOrderIds = RegistrationOrderItems::query()
                ->where('category_event_id', 2182)
                ->where('player_id', 5332)
                ->pluck('order_id');
            $hasPayfastEvidence = DB::table('transactions_pf')->where('event_id', 254)
                ->where(function ($query) use ($mastersOrderIds): void {
                    $query->where('player_id', 5332)->orWhere('custom_int2', 5332);
                    if ($mastersOrderIds->isNotEmpty()) {
                        $query->orWhereIn('custom_int5', $mastersOrderIds);
                    }
                })->exists();
            if ($hasPayfastEvidence) {
                throw ValidationException::withMessages(['payment' => 'Dirkie already has Masters PayFast evidence; manual financial review is required.']);
            }
            if ($mastersOrderIds->isNotEmpty()
                || DB::table('category_event_registrations as cer')
                    ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
                    ->where('cer.category_event_id', 2182)
                    ->where('pr.player_id', 5332)
                    ->exists()) {
                throw ValidationException::withMessages(['payment' => 'Dirkie already has Masters registration or payment evidence; manual financial review is required.']);
            }

            $existing = MastersInvitation::query()
                ->where('batch_id', 4)
                ->where('player_id', 5332)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if (! $this->isExactIdentityCorrectionReplacement($existing, $lockedVacancy)) {
                    throw ValidationException::withMessages(['invitation' => 'A different Masters invitation already exists for Dirkie; no replacement was created.']);
                }
            }

            $recipient = $this->playerUser($lockedTarget);
            if (! $recipient?->email || (int) $recipient->id !== 4025) {
                throw ValidationException::withMessages(['player' => 'Dirkie must have the audited linked account and email before an invitation can be queued.']);
            }

            $created = ! $existing;
            $now = now();
            $replacement = $existing ?: MastersInvitation::query()->create([
                'batch_id' => 4,
                'event_id' => 254,
                'category_event_id' => 2182,
                'ranking_list_id' => $lockedVacancy->ranking_list_id,
                'ranking_category_id' => $lockedVacancy->ranking_category_id,
                'player_id' => 5332,
                'registration_id' => null,
                'order_id' => null,
                'ranking_position' => $targetRanking->rank_position,
                'queue_position' => $lockedVacancy->queue_position,
                'total_points' => $targetRanking->total_points,
                'status' => MastersInvitation::INVITED,
                'decline_reason' => null,
                'exception_reason' => null,
                'promoted_from_id' => 807,
                'invited_at' => $now,
                'accepted_at' => null,
                'paid_at' => null,
                'declined_at' => null,
                'expired_at' => null,
                'withdrawn_at' => null,
                'replacement_sent_at' => $now,
                'declined_by_user_id' => null,
                'decline_method' => null,
                'decline_confirmation_sent_at' => null,
                'decline_confirmed_at' => null,
                'admin_removed_by_user_id' => null,
                'admin_removed_at' => null,
                'snapshot_json' => [
                    'player_name' => $lockedTarget->full_name,
                    'rank_position' => (int) $targetRanking->rank_position,
                    'total_points' => (int) $targetRanking->total_points,
                    'events_played' => $eventsPlayed,
                    'events_played_evidence_source' => $eligibilityEvidence['source'],
                    'minimum_events_for_team_selection' => $minimumEvents,
                    'published_ranking_id' => (int) $targetRanking->id,
                    'identity_correction' => [
                        'source_invitation_id' => 807,
                        'source_player_id' => 2439,
                        'target_player_id' => 5332,
                        'corrected_registration_ids' => $registrationIds,
                    ],
                ],
            ]);

            $correctionKey = 'wilson-series-u9-identity-2439-to-5332';
            $log = BulkEmailLog::query()->where('mail_type', 'masters_invitation')
                ->where('related_type', MastersInvitation::class)
                ->where('related_id', $replacement->id)
                ->where('payload->correction_key', $correctionKey)
                ->lockForUpdate()
                ->first();
            if (! $log) {
                $log = BulkEmailLog::create([
                    'mail_type' => 'masters_invitation',
                    'related_type' => MastersInvitation::class,
                    'related_id' => $replacement->id,
                    'recipient_email' => $recipient->email,
                    'recipient_name' => $lockedTarget->full_name,
                    'status' => 'queued',
                    'payload' => [
                        'invitation_id' => $replacement->id,
                        'kind' => 'replacement',
                        'correction_key' => $correctionKey,
                    ],
                    'queued_at' => $now,
                ]);
            }
            if (in_array($log->status, ['queued', 'failed'], true)) {
                $logId = (int) $log->id;
                DB::afterCommit(static function () use ($logId): void {
                    SendMastersInvitationEmailJob::dispatch($logId, 254);
                });
            }

            if ($created) {
                activity('masters')->performedOn($replacement)->causedBy($actor)
                    ->withProperties([
                        'vacancy_invitation_id' => 807,
                        'replacement_invitation_id' => $replacement->id,
                        'source_player_id' => 2439,
                        'target_player_id' => 5332,
                        'published_ranking_id' => $targetRanking->id,
                        'correction_key' => $correctionKey,
                    ])->log('Masters replacement invitation created after player identity correction');
            }

            return $replacement->fresh(['player', 'categoryEvent.category', 'batch.event']);
        });
    }

    /** @return array{events_played: int, source: string} */
    private function identityCorrectionEventsPlayed(SeriesRanking $ranking, array $registrationIds): array
    {
        $meta = is_array($ranking->meta_json) ? $ranking->meta_json : [];
        $hasExplicitCount = array_key_exists('events_played', $meta);
        $hasCountingLegs = array_key_exists('counting_legs', $meta);
        $explicitCount = null;

        if ($hasExplicitCount) {
            if (! is_int($meta['events_played']) && ! (is_string($meta['events_played']) && ctype_digit($meta['events_played']))) {
                throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking has malformed Masters event-count evidence.']);
            }
            $explicitCount = (int) $meta['events_played'];
        }

        $legCount = null;
        if ($hasCountingLegs) {
            if (! is_array($meta['counting_legs']) || ! array_is_list($meta['counting_legs'])) {
                throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking has malformed Masters counting-leg evidence.']);
            }

            $eventIds = [];
            foreach ($meta['counting_legs'] as $leg) {
                if (! is_array($leg)
                    || ! array_key_exists('category_event_id', $leg)
                    || (! is_int($leg['category_event_id']) && ! (is_string($leg['category_event_id']) && ctype_digit($leg['category_event_id'])))
                    || (int) $leg['category_event_id'] < 1) {
                    throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking has malformed Masters counting-leg evidence.']);
                }
                $eventIds[] = (int) $leg['category_event_id'];
            }

            if (count($eventIds) !== count(array_unique($eventIds))) {
                throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking has duplicate Masters counting-leg evidence.']);
            }
            sort($eventIds);
            if ($eventIds !== [1861, 2011]) {
                throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking does not contain the audited Wilson Series event legs.']);
            }

            $expectedRegistrations = [
                1861 => ['registration_id' => 18445, 'event_id' => 230],
                2011 => ['registration_id' => 20410, 'event_id' => 237],
            ];
            foreach ($expectedRegistrations as $categoryEventId => $expected) {
                $registrationId = $expected['registration_id'];
                if (! in_array($registrationId, $registrationIds, true)
                    || ! DB::table('category_event_registrations as cer')
                        ->join('category_events as ce', 'ce.id', '=', 'cer.category_event_id')
                        ->join('events as e', 'e.id', '=', 'ce.event_id')
                        ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
                        ->where('cer.category_event_id', $categoryEventId)
                        ->where('cer.registration_id', $registrationId)
                        ->where('cer.user_id', 4025)
                        ->where('cer.status', 'active')
                        ->where('cer.payment_status_id', 1)
                        ->where('ce.event_id', $expected['event_id'])
                        ->where('ce.category_id', 131)
                        ->where('e.series_id', 18)
                        ->where('pr.player_id', 5332)
                        ->exists()) {
                    throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking event legs no longer match the audited Wilson registrations.']);
                }
            }
            $legCount = count($eventIds);
        }

        if ($explicitCount !== null && $legCount !== null && $explicitCount !== $legCount) {
            throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking has conflicting Masters event-count evidence.']);
        }
        if ($explicitCount !== null) {
            return ['events_played' => $explicitCount, 'source' => $legCount === null ? 'events_played' : 'events_played_and_counting_legs'];
        }
        if ($legCount !== null) {
            return ['events_played' => $legCount, 'source' => 'counting_legs'];
        }

        throw ValidationException::withMessages(['ranking' => 'Dirkie\'s published ranking does not contain Masters event-count evidence.']);
    }

    private function isExactIdentityCorrectionReplacement(MastersInvitation $invitation, MastersInvitation $vacancy): bool
    {
        $correction = $invitation->snapshot_json['identity_correction'] ?? null;

        return (int) $invitation->event_id === 254
            && (int) $invitation->category_event_id === 2182
            && (int) $invitation->ranking_list_id === (int) $vacancy->ranking_list_id
            && (int) $invitation->ranking_category_id === (int) $vacancy->ranking_category_id
            && (int) $invitation->ranking_position === (int) $vacancy->ranking_position
            && (int) $invitation->queue_position === (int) $vacancy->queue_position
            && (int) $invitation->total_points === (int) $vacancy->total_points
            && $invitation->status === MastersInvitation::INVITED
            && (int) $invitation->promoted_from_id === 807
            && $invitation->invited_at !== null
            && $invitation->replacement_sent_at !== null
            && $invitation->registration_id === null
            && $invitation->order_id === null
            && $invitation->accepted_at === null
            && $invitation->paid_at === null
            && $invitation->declined_at === null
            && $invitation->expired_at === null
            && $invitation->withdrawn_at === null
            && $invitation->decline_reason === null
            && $invitation->exception_reason === null
            && is_array($correction)
            && (int) ($correction['source_invitation_id'] ?? 0) === 807
            && (int) ($correction['source_player_id'] ?? 0) === 2439
            && (int) ($correction['target_player_id'] ?? 0) === 5332
            && collect($correction['corrected_registration_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all() === [18445, 20410];
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

    public function markPaidByAdmin(MastersInvitation $invitation, User $actor): MastersInvitation
    {
        return DB::transaction(function () use ($invitation, $actor) {
            $locked = MastersInvitation::query()
                ->with(['batch.event', 'categoryEvent'])
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            if ($locked->status === MastersInvitation::PAID_CONFIRMED) {
                $existingEntry = CategoryEventRegistration::query()
                    ->where('registration_id', $locked->registration_id)
                    ->where('category_event_id', $locked->category_event_id)
                    ->where('status', 'active')
                    ->where('payment_status_id', 1)
                    ->first();

                if ($existingEntry?->isAdminEntry() && $existingEntry->admin_payment_status === 'paid') {
                    return $locked;
                }

                throw ValidationException::withMessages([
                    'invitation' => 'This invitation is already paid through another payment path.',
                ]);
            }

            if ($locked->status !== MastersInvitation::ACCEPTED_PENDING_PAYMENT) {
                throw ValidationException::withMessages([
                    'invitation' => 'Only a payment-pending Masters invitation can be marked as paid by an admin.',
                ]);
            }

            if (! $locked->categoryEvent
                || (int) $locked->categoryEvent->event_id !== (int) $locked->event_id
                || (int) $locked->batch->event_id !== (int) $locked->event_id) {
                throw ValidationException::withMessages([
                    'invitation' => 'The invitation does not belong to this event category.',
                ]);
            }

            $cancelledOrderId = $locked->order_id;
            $replacedRegistrationId = $locked->registration_id;

            if (! $locked->order_id || ! $locked->registration_id) {
                throw ValidationException::withMessages([
                    'invitation' => 'The pending Masters checkout is incomplete and cannot be marked as paid.',
                ]);
            }

            $order = RegistrationOrder::query()->lockForUpdate()->findOrFail($locked->order_id);
            $orderItems = RegistrationOrderItems::query()
                ->where('order_id', $order->id)
                ->get();
            $orderItemMatches = $orderItems
                ->where('registration_id', $locked->registration_id)
                ->where('player_id', $locked->player_id)
                ->where('category_event_id', $locked->category_event_id)
                ->count() === 1;

            if (! $orderItemMatches || $orderItems->count() !== 1) {
                throw ValidationException::withMessages([
                    'invitation' => 'The pending checkout does not exclusively match this invitation.',
                ]);
            }

            if ($order->pay_status || $order->payfast_paid || $order->wallet_debited) {
                throw ValidationException::withMessages([
                    'invitation' => 'This checkout is already recorded as paid and must be reconciled instead.',
                ]);
            }

            // A legacy admin entry may already be the canonical active entry for
            // this player/category. Resolve that state before cancelling the
            // pending checkout; creating another admin entry would duplicate the
            // roster row and its zero-value audit transaction.
            $activePaidEntries = CategoryEventRegistration::query()
                ->select('category_event_registrations.*')
                ->join('player_registrations as masters_pr', 'masters_pr.registration_id', '=', 'category_event_registrations.registration_id')
                ->where('masters_pr.player_id', $locked->player_id)
                ->where('category_event_registrations.category_event_id', $locked->category_event_id)
                ->where('category_event_registrations.status', 'active')
                ->where('category_event_registrations.payment_status_id', 1)
                ->whereNull('category_event_registrations.deleted_at')
                ->lockForUpdate()
                ->get();

            if ($paidItem = $this->paidOrderItemFor($locked)) {
                throw ValidationException::withMessages([
                    'invitation' => 'This player already has a gateway-paid entry. Reconcile that payment instead of recording a private payment.',
                ]);
            }

            if ($activePaidEntries->count() > 1) {
                throw ValidationException::withMessages([
                    'invitation' => 'Multiple active paid entries exist for this player and category. Resolve the duplicate records before marking a private payment.',
                ]);
            }

            $existingAdminEntry = $activePaidEntries->first();
            if ($existingAdminEntry) {
                $hasSafeAdminState = (int) $existingAdminEntry->registration_id !== (int) $locked->registration_id
                    && $existingAdminEntry->isAdminEntry()
                    && $existingAdminEntry->pf_transaction_id === null
                    && in_array($existingAdminEntry->refund_status, [null, '', 'not_refunded'], true)
                    && (float) ($existingAdminEntry->refund_gross ?? 0) === 0.0;

                $adminTransactions = DB::table('transactions_pf')
                    ->where('event_id', $locked->event_id)
                    ->where('category_event_id', $locked->category_event_id)
                    ->where('player_id', $locked->player_id)
                    ->whereNull('pf_payment_id')
                    ->where('item_name', 'Admin Entry')
                    ->when(
                        \Illuminate\Support\Facades\Schema::hasColumn('transactions_pf', 'archived_at'),
                        fn ($query) => $query->whereNull('archived_at')
                    )
                    ->lockForUpdate()
                    ->get();

                $allTransactions = DB::table('transactions_pf')
                    ->where('event_id', $locked->event_id)
                    ->where('category_event_id', $locked->category_event_id)
                    ->where('player_id', $locked->player_id)
                    ->when(
                        \Illuminate\Support\Facades\Schema::hasColumn('transactions_pf', 'archived_at'),
                        fn ($query) => $query->whereNull('archived_at')
                    )
                    ->lockForUpdate()
                    ->get();

                if (! $hasSafeAdminState
                    || $adminTransactions->count() !== 1
                    || $allTransactions->count() !== 1
                    || (float) $adminTransactions->first()->amount_gross !== 0.0) {
                    throw ValidationException::withMessages([
                        'invitation' => 'An existing paid entry cannot be safely identified as one unreconciled admin entry. Resolve its payment history before continuing.',
                    ]);
                }

                app(PaymentOrchestrator::class)->cancelPayment($order);
                $this->softDeleteUnpaidDraftEntry($locked->registration_id, $locked->category_event_id);

                if ($existingAdminEntry->admin_payment_status !== 'paid') {
                    $existingAdminEntry = app(EntryService::class)
                        ->setAdminPaymentStatus($existingAdminEntry, true, $actor);
                }

                $locked->update([
                    'registration_id' => $existingAdminEntry->registration_id,
                    'order_id' => null,
                    'status' => MastersInvitation::PAID_CONFIRMED,
                    'paid_at' => now(),
                ]);

                activity('masters')->performedOn($locked)->causedBy($actor)
                    ->withProperties([
                        'invitation_id' => $locked->id,
                        'player_id' => $locked->player_id,
                        'category_event_id' => $locked->category_event_id,
                        'cancelled_order_id' => $cancelledOrderId,
                        'replaced_registration_id' => $replacedRegistrationId,
                        'admin_registration_id' => $existingAdminEntry->registration_id,
                        'collection_status' => 'paid_privately',
                        'reconciled_payment' => false,
                        'linked_existing_admin_entry' => true,
                    ])->log('Masters invitation linked to existing admin entry and marked paid privately (not reconciled)');

                return $locked->refresh();
            }

            app(PaymentOrchestrator::class)->cancelPayment($order);

            $this->softDeleteUnpaidDraftEntry($locked->registration_id, $locked->category_event_id);
            $entry = app(EntryService::class)->addPlayerAsAdmin(
                $locked->categoryEvent,
                $locked->player_id,
                $actor,
                'paid_privately',
            );

            $locked->update([
                'registration_id' => $entry->registration_id,
                'order_id' => null,
                'status' => MastersInvitation::PAID_CONFIRMED,
                'paid_at' => now(),
            ]);

            activity('masters')->performedOn($locked)->causedBy($actor)
                ->withProperties([
                    'invitation_id' => $locked->id,
                    'player_id' => $locked->player_id,
                    'category_event_id' => $locked->category_event_id,
                    'cancelled_order_id' => $cancelledOrderId,
                    'replaced_registration_id' => $replacedRegistrationId,
                    'admin_registration_id' => $entry->registration_id,
                    'collection_status' => 'paid_privately',
                    'reconciled_payment' => false,
                ])->log('Masters invitation marked paid by admin (private collection not reconciled)');

            return $locked->refresh();
        });
    }

    /**
     * Reconcile Masters invitation state against canonical paid orders and
     * release abandoned unpaid checkouts. Preview is the default so this can
     * be run safely on production before applying changes.
     */
    public function reconcilePaymentStates(?int $eventId, int $pendingMinutes, bool $apply = false): array
    {
        $pendingMinutes = max(1, $pendingMinutes);
        $cutoff = now()->subMinutes($pendingMinutes);
        $rows = [];

        $query = MastersInvitation::query()
            ->with('player')
            ->whereIn('status', [
                MastersInvitation::INVITED,
                MastersInvitation::ACCEPTED_PENDING_PAYMENT,
            ])
            ->whereHas('batch', fn ($batch) => $batch->where('status', 'sent'))
            ->orderBy('id');

        if ($eventId !== null) {
            $query->where('event_id', $eventId);
        }

        $query->chunkById(200, function ($invitations) use (&$rows, $apply, $cutoff) {
            foreach ($invitations as $invitation) {
                $paidItem = $this->paidOrderItemFor($invitation);

                if ($paidItem) {
                    $rows[] = [
                        'invitation_id' => $invitation->id,
                        'player' => $invitation->player?->full_name ?? "Player {$invitation->player_id}",
                        'action' => 'link_paid_registration',
                        'order_id' => $paidItem->order_id,
                        'registration_id' => $paidItem->registration_id,
                    ];
                    if ($apply) {
                        $this->reconcilePaidInvitation($invitation);
                    }
                    continue;
                }

                if ($invitation->status === MastersInvitation::ACCEPTED_PENDING_PAYMENT
                    && $invitation->accepted_at
                    && $invitation->accepted_at->lte($cutoff)) {
                    $rows[] = [
                        'invitation_id' => $invitation->id,
                        'player' => $invitation->player?->full_name ?? "Player {$invitation->player_id}",
                        'action' => 'return_to_register',
                        'order_id' => $invitation->order_id,
                        'registration_id' => $invitation->registration_id,
                    ];
                    if ($apply) {
                        $this->expireUnpaidCheckout($invitation, $cutoff);
                    }
                }
            }
        });

        return $rows;
    }

    private function reconcilePaidInvitation(MastersInvitation $invitation): void
    {
        $candidate = $this->paidOrderItemFor($invitation);
        if (!$candidate) return;

        DB::transaction(function () use ($invitation, $candidate) {
            $paidOrder = RegistrationOrder::query()->lockForUpdate()->find($candidate->order_id);
            if (!$paidOrder || (!$paidOrder->pay_status && !$paidOrder->payfast_paid)) return;

            $locked = MastersInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $paidItem = $this->paidOrderItemFor($locked);
            if (!$paidItem || (int) $paidItem->order_id !== (int) $paidOrder->id) return;

            $duplicateItems = RegistrationOrderItems::query()
                ->where('player_id', $locked->player_id)
                ->where('category_event_id', $locked->category_event_id)
                ->where('registration_id', '!=', $paidItem->registration_id)
                ->get();

            foreach ($duplicateItems as $duplicateItem) {
                $duplicateOrder = RegistrationOrder::query()->lockForUpdate()->find($duplicateItem->order_id);
                if ($duplicateOrder && !$duplicateOrder->pay_status && !$duplicateOrder->payfast_paid) {
                    app(PaymentOrchestrator::class)->cancelPayment($duplicateOrder);
                    $this->softDeleteUnpaidDraftEntry($duplicateItem->registration_id, $duplicateItem->category_event_id);
                }
            }

            $locked->update([
                'registration_id' => $paidItem->registration_id,
                'order_id' => $paidItem->order_id,
                'status' => MastersInvitation::PAID_CONFIRMED,
                'accepted_at' => $locked->accepted_at ?? $paidItem->created_at,
                'paid_at' => $paidItem->order_updated_at ?? $paidItem->created_at ?? now(),
            ]);

            activity('masters')->performedOn($locked)
                ->withProperties([
                    'invitation_id' => $locked->id,
                    'player_id' => $locked->player_id,
                    'order_id' => $paidItem->order_id,
                    'registration_id' => $paidItem->registration_id,
                ])->log('reconciled Masters invitation to existing paid registration');
        });
    }

    private function expireUnpaidCheckout(MastersInvitation $invitation, $cutoff): void
    {
        if (!$invitation->order_id) return;

        DB::transaction(function () use ($invitation, $cutoff) {
            $order = RegistrationOrder::query()->lockForUpdate()->find($invitation->order_id);
            if (!$order || $order->pay_status || $order->payfast_paid) return;

            $locked = MastersInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if ($locked->status !== MastersInvitation::ACCEPTED_PENDING_PAYMENT
                || !$locked->accepted_at
                || $locked->accepted_at->gt($cutoff)
                || (int) $locked->order_id !== (int) $order->id) {
                return;
            }

            app(PaymentOrchestrator::class)->cancelPayment($order);
            $this->softDeleteUnpaidDraftEntry($locked->registration_id, $locked->category_event_id);
            $oldOrderId = $locked->order_id;
            $oldRegistrationId = $locked->registration_id;
            $locked->update([
                'status' => MastersInvitation::INVITED,
                'order_id' => null,
                'registration_id' => null,
                'accepted_at' => null,
            ]);

            activity('masters')->performedOn($locked)
                ->withProperties([
                    'invitation_id' => $locked->id,
                    'player_id' => $locked->player_id,
                    'order_id' => $oldOrderId,
                    'registration_id' => $oldRegistrationId,
                ])->log('expired unpaid Masters checkout and returned invitation to register');
        });
    }

    private function paidOrderItemFor(MastersInvitation $invitation): ?object
    {
        return RegistrationOrderItems::query()
            ->from('registration_order_items as item')
            ->join('registration_orders as orders', 'orders.id', '=', 'item.order_id')
            ->join('category_event_registrations as entry', function ($join) {
                $join->on('entry.registration_id', '=', 'item.registration_id')
                    ->on('entry.category_event_id', '=', 'item.category_event_id');
            })
            ->where('item.player_id', $invitation->player_id)
            ->where('item.category_event_id', $invitation->category_event_id)
            ->whereNull('entry.deleted_at')
            ->where('entry.status', 'active')
            ->where('entry.payment_status_id', 1)
            ->where(fn ($query) => $query->where('orders.pay_status', 1)->orWhere('orders.payfast_paid', 1))
            ->orderByDesc('orders.updated_at')
            ->select([
                'item.order_id',
                'item.registration_id',
                'item.created_at',
                'orders.updated_at as order_updated_at',
            ])
            ->first();
    }

    private function softDeleteUnpaidDraftEntry(?int $registrationId, int $categoryEventId): void
    {
        if (!$registrationId) return;

        CategoryEventRegistration::query()
            ->where('registration_id', $registrationId)
            ->where('category_event_id', $categoryEventId)
            ->where(fn ($query) => $query->whereNull('payment_status_id')->orWhere('payment_status_id', 0))
            ->whereNull('pf_transaction_id')
            ->where(fn ($query) => $query->whereNull('refund_gross')->orWhere('refund_gross', 0))
            ->get()
            ->each->delete();
    }

    public function handlePaidWithdrawal(
        int $registrationId,
        ?User $actor = null,
        bool $sendNotifications = true
    ): ?MastersInvitation
    {
        return DB::transaction(function () use ($registrationId, $actor, $sendNotifications) {
            $invitation = MastersInvitation::query()->lockForUpdate()
                ->where('registration_id', $registrationId)->where('status', MastersInvitation::PAID_CONFIRMED)->first();
            if (!$invitation) return null;
            $entry = CategoryEventRegistration::query()
                ->where('registration_id', $registrationId)
                ->where('category_event_id', $invitation->category_event_id)
                ->first();
            if (!$entry || $entry->status !== 'withdrawn') {
                throw new \RuntimeException('A paid Masters invitation can only be withdrawn after its entry is withdrawn.');
            }
            $invitation->update([
                'status' => MastersInvitation::WITHDRAWN,
                'withdrawn_at' => $entry->withdrawn_at ?: now(),
            ]);
            if ($actor) $this->recordActor($invitation, $actor, 'withdrew Masters registration');
            if ($sendNotifications) {
                DB::afterCommit(function () use ($invitation) {
                    $fresh = $invitation->fresh(['player', 'batch.event', 'categoryEvent.category']);
                    $this->queuePlayerMail($fresh, 'withdrawn');
                    $this->queueAdminMail($fresh, 'withdrawn');
                });
            }
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
