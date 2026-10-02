<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use Illuminate\Http\Request;

class SuperAdminMailHistory
{
    public const STATUSES = ['queued', 'sending', 'sent', 'failed', 'skipped', 'acceptance_unknown'];

    public function data(Request $request): array
    {
        $filters = $request->validate([
            'mail_recipient' => ['nullable', 'string', 'max:255'],
            'mail_subject' => ['nullable', 'string', 'max:255'],
            'mail_type' => ['nullable', 'string', 'max:100'],
            'mail_status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'mail_from' => ['nullable', 'date_format:Y-m-d'],
            'mail_until' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('mail_from') ? 'after_or_equal:mail_from' : null]),
        ]);
        $subjectExpression = \Illuminate\Support\Facades\DB::getDriverName() === 'sqlite'
            ? "COALESCE(JSON_EXTRACT(payload, '$.rendered_subject'), JSON_EXTRACT(payload, '$.subject'))"
            : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.rendered_subject')), JSON_UNQUOTE(JSON_EXTRACT(payload, '$.subject')))";
        $query = BulkEmailLog::query()
            // Do not retrieve bodies, signed links, credentials or stored errors.
            ->select(['id', 'mail_type', 'recipient_email', 'recipient_name', 'status', 'created_at', 'queued_at', 'sent_at', 'accepted_at', 'failed_at', 'skipped_at', 'evidence_status'])
            ->selectRaw($subjectExpression.' AS history_subject');
        if (! empty($filters['mail_recipient'])) {
            $query->where('recipient_email', 'like', '%'.$filters['mail_recipient'].'%');
        }
        if (! empty($filters['mail_subject'])) {
            $query->whereRaw($subjectExpression.' LIKE ?', ['%'.$filters['mail_subject'].'%']);
        }
        foreach (['mail_type' => 'mail_type', 'mail_status' => 'status'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['mail_from'])) {
            $query->where('created_at', '>=', $filters['mail_from'].' 00:00:00');
        }
        if (! empty($filters['mail_until'])) {
            $query->where('created_at', '<', \Carbon\Carbon::parse($filters['mail_until'])->addDay()->toDateString());
        }

        return [
            'mailLogs' => $query->orderByDesc('id')->paginate(25, ['*'], 'mail_page')->withQueryString()
                ->appends($request->routeIs('backend.superadmin.workspace') ? ['tab' => 'mails'] : []),
            'mailFilters' => $filters,
            'mailStatuses' => self::STATUSES,
        ];
    }
}
