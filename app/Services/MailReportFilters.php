<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Filtering never grants access: callers supply an already authorized query. */
class MailReportFilters
{
    public const OUTCOMES = [
        'sent_complete' => 'Sent', 'accepted' => 'Server accepted', 'failed' => 'Failed',
        'pending' => 'Pending', 'skipped' => 'Excluded', 'uncertain' => 'Acceptance uncertain',
        'unverified' => 'Sent, evidence unverified', 'sandbox' => 'Sandbox accepted',
        'queued' => 'Queued', 'sending' => 'Sending', 'sent' => 'Completed, including sandbox',
    ];

    public function validate(Request $request, string $prefix = ''): array
    {
        $rules = [
            'outcome' => 'nullable|in:'.implode(',', array_keys(self::OUTCOMES)),
            'search' => 'nullable|string|max:200', 'mail_type' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d',
            'until' => ['nullable', 'date_format:Y-m-d'],
        ];
        if ($request->filled($prefix.'from')) {
            $rules['until'][] = 'after_or_equal:'.$prefix.'from';
        }
        $validated = $request->validate(collect($rules)->mapWithKeys(fn ($rule, $key) => [($prefix === 'mail_' && $key === 'mail_type' ? 'mail_type' : $prefix.$key) => $rule])->all());

        return collect($validated)->mapWithKeys(fn ($value, $key) => [($prefix === 'mail_' && $key === 'mail_type' ? 'mail_type' : substr($key, strlen($prefix))) => $value])->all();
    }

    public function apply(Builder $query, array $filters, bool $outcome = true): Builder
    {
        if (! empty($filters['mail_type'])) {
            $query->where('mail_type', $filters['mail_type']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.addcslashes($filters['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('recipient_name', 'like', $term)->orWhere('recipient_email', 'like', $term)
                ->orWhere('payload->subject', 'like', $term)->orWhere('payload->rendered_subject', 'like', $term)->orWhere('payload->title', 'like', $term));
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['until'])) {
            $query->where('created_at', '<', \Carbon\Carbon::parse($filters['until'])->addDay()->toDateString());
        }
        if ($outcome && ! empty($filters['outcome'])) {
            $this->outcome($query, $filters['outcome']);
        }

        return $query;
    }

    public function outcome(Builder $query, string $outcome): Builder
    {
        match ($outcome) {
            'sent_complete' => $query->where('status', 'sent')->where(fn ($q) => $q->whereNull('evidence_status')->orWhere('evidence_status', '!=', 'sandbox_accepted')),
            'pending' => $query->whereIn('status', ['queued', 'sending']),
            'accepted' => $query->where('status', 'sent')->where('evidence_status', 'server_accepted')->whereNotNull('accepted_at'),
            'sandbox' => $query->where('status', 'sent')->where('evidence_status', 'sandbox_accepted')->whereNotNull('accepted_at'),
            'unverified' => $query->where('status', 'sent')->where(fn ($q) => $q->whereNull('accepted_at')->orWhereNull('evidence_status')->orWhereNotIn('evidence_status', ['server_accepted', 'sandbox_accepted'])),
            'uncertain' => $query->where('status', 'acceptance_unknown'),
            default => $query->where('status', $outcome),
        };

        return $query;
    }

    public function data(Request $request, Builder $authorized, string $prefix = 'history_', string $page = 'history_page'): array
    {
        $filters = $this->validate($request, $prefix);
        $scope = $this->apply(clone $authorized, $filters, false);
        $summary = BulkEmailLog::deliverySummary((clone $scope)->select([]));
        $counts = ['all' => $summary['total']];
        foreach (['sent_complete', 'accepted', 'failed', 'pending', 'skipped', 'uncertain'] as $outcome) {
            $counts[$outcome] = $this->outcome((clone $scope)->reorder(), $outcome)->count();
        }
        $copies = (clone $scope)->whereIn('payload->recipient_kind', ['admin_copy', 'sender_copy'])->count();
        $types = (clone $authorized)->reorder()->select('mail_type')->distinct()->whereNotNull('mail_type')->orderBy('mail_type')->limit(100)->pluck('mail_type');
        if (! empty($filters['mail_type']) && ! $types->contains($filters['mail_type'])) {
            $types->push($filters['mail_type']);
        }

        return [
            'logs' => $this->apply(clone $authorized, $filters)->latest('id')->paginate(25, ['*'], $page)->withQueryString(),
            'filters' => $filters, 'counts' => $counts, 'summary' => $summary,
            'copyCount' => $copies, 'recipientCount' => $summary['total'] - $copies,
            'types' => $types->mapWithKeys(fn ($type) => [$type => SuperAdminMailHistory::typeLabel($type)]),
            'prefix' => $prefix, 'page' => $page,
        ];
    }
}
