<?php

namespace App\Domain\Ranking\Services;

use App\Jobs\SendBulkEmailJob;
use App\Models\BulkEmailLog;
use App\Models\RankingReviewCampaign;
use App\Models\RankingReviewRecipient;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RankingReviewCirculationService
{
    public function defaults(Series $series): array
    {
        $cutoff = now()->addHours((int) ($series->ranking_review_default_hours ?: 24));

        return [
            'uuid' => (string) Str::uuid(),
            'cutoff_at' => $cutoff,
            'subject' => "Provisional {$series->name} rankings - please check",
            'message' => $this->defaultMessage($series),
        ];
    }

    public function preview(Series $series): array
    {
        $runId = $this->reviewedRunId($series);
        $rows = $this->runRows($series, $runId);
        $audience = $this->audience($rows);

        return [
            'run_id' => $runId,
            'snapshot_hash' => $this->snapshotHash($rows),
            'audience' => $audience,
            'defaults' => $this->defaults($series),
        ];
    }

    public function send(
        Series $series,
        User $actor,
        string $uuid,
        string $subject,
        string $message,
        string $replyTo,
        CarbonInterface $cutoff,
    ): RankingReviewCampaign {
        return DB::transaction(function () use ($series, $actor, $uuid, $subject, $message, $replyTo, $cutoff): RankingReviewCampaign {
            DB::table('series')->where('id', $series->id)->lockForUpdate()->first();

            if ($existing = RankingReviewCampaign::where('uuid', $uuid)->first()) {
                if ((int) $existing->series_id !== (int) $series->id) {
                    throw ValidationException::withMessages(['campaign' => 'That circulation token belongs to another series.']);
                }

                return $existing;
            }

            $runId = $this->reviewedRunId($series);
            if (RankingReviewCampaign::where('series_id', $series->id)->where('run_id', $runId)->exists()) {
                throw ValidationException::withMessages(['campaign' => 'This ranking run has already been circulated. Refresh to view its delivery status.']);
            }

            $rows = $this->runRows($series, $runId);
            $audience = $this->audience($rows);
            if ($audience['recipient_count'] === 0) {
                throw ValidationException::withMessages(['recipients' => 'No valid recipient email addresses were found for this ranking run.']);
            }

            $campaign = RankingReviewCampaign::create([
                'uuid' => $uuid,
                'series_id' => $series->id,
                'run_id' => $runId,
                'snapshot_hash' => $this->snapshotHash($rows),
                'status' => 'queued',
                'subject' => $subject,
                'message' => $message,
                'reply_to' => $replyTo,
                'cutoff_at' => $cutoff,
                'sent_by' => $actor->id,
                'sent_at' => now(),
                'player_count' => $audience['player_count'],
                'recipient_count' => $audience['recipient_count'],
                'missing_email_count' => $audience['missing_email_count'],
            ]);

            foreach ($audience['recipients'] as $recipientData) {
                $recipient = RankingReviewRecipient::create([
                    'ranking_review_campaign_id' => $campaign->id,
                    'email' => $recipientData['email'],
                    'player_ids' => $recipientData['player_ids'],
                    'player_names' => $recipientData['player_names'],
                    'category_names' => $recipientData['category_names'],
                    'status' => 'queued',
                ]);

                $log = BulkEmailLog::create([
                    'mail_type' => 'ranking_review',
                    'related_type' => RankingReviewCampaign::class,
                    'related_id' => $campaign->id,
                    'recipient_email' => $recipientData['email'],
                    'recipient_name' => implode(' / ', $recipientData['player_names']),
                    'status' => 'queued',
                    'payload' => ['campaign_id' => $campaign->id],
                    'queued_at' => now(),
                ]);

                $recipient->update(['bulk_email_log_id' => $log->id]);
                DB::afterCommit(static fn () => SendBulkEmailJob::dispatch($log->id));
            }

            foreach ($audience['missing'] as $missing) {
                RankingReviewRecipient::create([
                    'ranking_review_campaign_id' => $campaign->id,
                    'email' => null,
                    'player_ids' => [$missing['player_id']],
                    'player_names' => [$missing['name']],
                    'category_names' => $missing['categories'],
                    'status' => 'missing',
                    'error_message' => 'No valid linked account or player email address.',
                ]);
            }

            activity('ranking')
                ->performedOn($campaign)
                ->causedBy($actor)
                ->withProperties([
                    'series_id' => $series->id,
                    'run_id' => $runId,
                    'snapshot_hash' => $campaign->snapshot_hash,
                    'cutoff_at' => $cutoff->toIso8601String(),
                    'player_count' => $campaign->player_count,
                    'recipient_count' => $campaign->recipient_count,
                    'missing_email_count' => $campaign->missing_email_count,
                ])
                ->log('Provisional ranking circulated for participant review');

            return $campaign;
        });
    }

    public function deliveryReport(RankingReviewCampaign $campaign): array
    {
        $logs = BulkEmailLog::where('related_type', RankingReviewCampaign::class)
            ->where('related_id', $campaign->id)
            ->get();
        $counts = collect(['queued', 'sent', 'failed', 'skipped'])
            ->mapWithKeys(fn (string $status): array => [$status => $logs->where('status', $status)->count()])
            ->all();

        $effectiveStatus = $campaign->status === 'queued' && $counts['queued'] === 0
            ? 'sent'
            : $campaign->status;

        return [
            'campaign_id' => $campaign->id,
            'status' => $effectiveStatus,
            'run_id' => $campaign->run_id,
            'cutoff_at' => $campaign->cutoff_at?->toIso8601String(),
            'cutoff_display' => $campaign->cutoff_at?->timezone(config('app.timezone'))->format('d M Y H:i T'),
            'player_count' => $campaign->player_count,
            'recipient_count' => $campaign->recipient_count,
            'missing_email_count' => $campaign->missing_email_count,
            'delivery' => $counts,
            'missing' => $campaign->recipients()->where('status', 'missing')->get()
                ->map(fn (RankingReviewRecipient $recipient): array => [
                    'name' => $recipient->player_names[0] ?? 'Unknown player',
                    'categories' => $recipient->category_names ?? [],
                ])->values()->all(),
        ];
    }

    public function retryFailed(RankingReviewCampaign $campaign, User $actor): int
    {
        if (in_array($campaign->status, ['finalized', 'superseded'], true)) {
            throw ValidationException::withMessages(['campaign' => 'This circulation is closed and cannot be retried.']);
        }

        return DB::transaction(function () use ($campaign, $actor): int {
            $logs = BulkEmailLog::where('related_type', RankingReviewCampaign::class)
                ->where('related_id', $campaign->id)
                ->where('status', 'failed')
                ->lockForUpdate()
                ->get();

            foreach ($logs as $log) {
                $log->update([
                    'status' => 'queued',
                    'queued_at' => now(),
                    'failed_at' => null,
                    'error_message' => null,
                ]);
                RankingReviewRecipient::where('bulk_email_log_id', $log->id)->update([
                    'status' => 'queued',
                    'error_message' => null,
                ]);
                DB::afterCommit(static fn () => SendBulkEmailJob::dispatch($log->id));
            }

            if ($logs->isNotEmpty()) {
                $campaign->update(['status' => 'queued']);
                activity('ranking')->performedOn($campaign)->causedBy($actor)
                    ->withProperties(['retried' => $logs->count()])
                    ->log('Failed provisional ranking emails retried');
            }

            return $logs->count();
        });
    }

    public function assertReadyToFinalize(Series $series): ?RankingReviewCampaign
    {
        $runId = $this->reviewedRunId($series);
        $campaign = RankingReviewCampaign::where('series_id', $series->id)
            ->where('run_id', $runId)
            ->whereIn('status', ['queued', 'sent'])
            ->first();

        if (! $campaign) {
            return null;
        }
        if (now()->lt($campaign->cutoff_at)) {
            throw ValidationException::withMessages([
                'cutoff' => 'Participant review remains open until '.$campaign->cutoff_at->format('d M Y H:i T').'.',
            ]);
        }

        $delivery = $this->deliveryReport($campaign)['delivery'];
        if ($delivery['queued'] > 0 || $delivery['failed'] > 0) {
            throw ValidationException::withMessages([
                'delivery' => "Resolve ranking email delivery first: {$delivery['queued']} queued and {$delivery['failed']} failed.",
            ]);
        }

        if (! hash_equals($campaign->snapshot_hash, $this->snapshotHash($this->runRows($series, $runId)))) {
            $campaign->update(['status' => 'superseded']);
            throw ValidationException::withMessages(['ranking' => 'The circulated ranking snapshot has changed. Rebuild, review and circulate the replacement run.']);
        }

        return $campaign;
    }

    public function markFinalized(?RankingReviewCampaign $campaign, User $actor): void
    {
        if (! $campaign) {
            return;
        }

        $campaign->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => $actor->id,
        ]);
        activity('ranking')->performedOn($campaign)->causedBy($actor)
            ->log('Circulated ranking finalized and published');
    }

    public function supersedeOpenCampaigns(Series $series): void
    {
        RankingReviewCampaign::where('series_id', $series->id)
            ->whereIn('status', ['queued', 'sent'])
            ->update(['status' => 'superseded']);
    }

    public function signedPublicUrl(RankingReviewCampaign $campaign): string
    {
        return URL::signedRoute('ranking.review.public', ['campaign' => $campaign->uuid]);
    }

    public function snapshotMatches(RankingReviewCampaign $campaign): bool
    {
        $campaign->loadMissing('series');

        return hash_equals(
            $campaign->snapshot_hash,
            $this->snapshotHash($this->runRows($campaign->series, $campaign->run_id)),
        );
    }

    public function reviewedRunId(Series $series): string
    {
        $runs = SeriesRanking::where('series_id', $series->id)
            ->where('status', 'reviewed')
            ->whereNotNull('run_id')
            ->distinct()
            ->pluck('run_id');

        if ($runs->count() !== 1) {
            throw ValidationException::withMessages([
                'ranking' => "Expected exactly one reviewed ranking run; found {$runs->count()}.",
            ]);
        }

        return (string) $runs->first();
    }

    public function runRows(Series $series, string $runId): Collection
    {
        return SeriesRanking::with(['player.user', 'player.users', 'category'])
            ->where('series_id', $series->id)
            ->where('run_id', $runId)
            ->whereIn('status', ['reviewed', 'published'])
            ->orderBy('category_id')
            ->orderBy('rank_position')
            ->orderBy('player_id')
            ->get();
    }

    public function snapshotHash(Collection $rows): string
    {
        $snapshot = $rows->map(fn (SeriesRanking $row): array => [
            'id' => $row->id,
            'ranking_list_id' => $row->ranking_list_id,
            'category_id' => $row->category_id,
            'player_id' => $row->player_id,
            'rank_position' => $row->rank_position,
            'total_points' => (string) $row->total_points,
            'meta_json' => $row->meta_json,
        ])->values()->all();

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function audience(Collection $rows): array
    {
        $players = $rows->groupBy('player_id')->map(function (Collection $playerRows): array {
            $row = $playerRows->first();
            $player = $row->player;
            $emails = collect([$player?->user?->email])
                ->merge($player?->users?->pluck('email') ?? collect())
                ->push($player?->email)
                ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
                ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
                ->unique()
                ->values();

            return [
                'player_id' => (int) $row->player_id,
                'name' => $player?->full_name ?: 'Unknown player',
                'email' => $emails->first(),
                'categories' => $playerRows->pluck('category.name')->filter()->unique()->values()->all(),
            ];
        })->values();

        $missing = $players->whereNull('email')->values();
        $recipients = $players->whereNotNull('email')->groupBy('email')->map(function (Collection $group, string $email): array {
            return [
                'email' => $email,
                'player_ids' => $group->pluck('player_id')->values()->all(),
                'player_names' => $group->pluck('name')->values()->all(),
                'category_names' => $group->pluck('categories')->flatten()->unique()->values()->all(),
            ];
        })->values();

        return [
            'player_count' => $players->count(),
            'recipient_count' => $recipients->count(),
            'missing_email_count' => $missing->count(),
            'shared_email_count' => $recipients->filter(fn (array $recipient): bool => count($recipient['player_ids']) > 1)->count(),
            'recipients' => $recipients->all(),
            'missing' => $missing->all(),
        ];
    }

    private function defaultMessage(Series $series): string
    {
        return "The provisional rankings for {$series->name} are ready for review.\n\n"
            .'Please use the link below to check your ranking position and points. '
            ."If you believe there is an issue, please reply to this email before the reply cutoff shown below.\n\n"
            .'If we do not hear from you by the cutoff, we will assume that you are satisfied with your ranking. '
            .'The rankings will then be finalized, and no further ordinary changes will be accepted. '
            .'Invitations for the next tournament will be prepared from the finalized rankings.';
    }
}
