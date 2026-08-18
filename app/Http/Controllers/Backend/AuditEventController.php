<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Support\Audit\AuditIntegrity;
use App\Support\Audit\AuditWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditEventController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request)->with('actor:id,name,email');
        $events = $query->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $categories = AuditEvent::query()->select('category')->distinct()->orderBy('category')->pluck('category');
        $actions = AuditEvent::query()->select('action')->distinct()->orderBy('action')->limit(250)->pluck('action');
        $stats = [
            'today' => AuditEvent::where('occurred_at', '>=', now()->startOfDay())->count(),
            'denied_7d' => AuditEvent::where('occurred_at', '>=', now()->subDays(7))->where('outcome', 'denied')->count(),
            'deletions_30d' => AuditEvent::where('occurred_at', '>=', now()->subDays(30))->where('action', 'like', '%.deleted')->count(),
            'users_30d' => AuditEvent::where('occurred_at', '>=', now()->subDays(30))->whereNotNull('actor_id')->distinct('actor_id')->count('actor_id'),
        ];

        return view('backend.superadmin.audit.index', compact('events', 'categories', 'actions', 'stats'));
    }

    public function show(AuditEvent $auditEvent)
    {
        $auditEvent->load('actor:id,name,email');
        $journey = collect();
        if ($auditEvent->journey_id) {
            $journey = AuditEvent::where('journey_id', $auditEvent->journey_id)
                ->orderBy('occurred_at')->orderBy('id')->limit(100)->get();
        }

        $subjectTimeline = collect();
        if ($auditEvent->subject_type && $auditEvent->subject_id) {
            $subjectTimeline = AuditEvent::where('subject_type', $auditEvent->subject_type)
                ->where('subject_id', $auditEvent->subject_id)
                ->orderByDesc('occurred_at')->limit(50)->get();
        }

        $integrityValid = AuditIntegrity::verify($auditEvent->getRawOriginal());

        return view('backend.superadmin.audit.show', compact(
            'auditEvent', 'journey', 'subjectTimeline', 'integrityValid'
        ));
    }

    public function export(Request $request, AuditWriter $writer): StreamedResponse
    {
        $writer->record([
            'category' => 'security',
            'action' => 'audit.exported',
            'metadata' => ['filters' => $request->query(), 'maximum_rows' => 10000],
        ], true);

        $rows = $this->filteredQuery($request)
            ->orderByDesc('occurred_at')->orderByDesc('id')->limit(10000)->cursor();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, [
                'Event UUID', 'Occurred At', 'Category', 'Action', 'Outcome', 'Actor ID',
                'Actor', 'Email', 'Subject Type', 'Subject ID', 'Event ID', 'Route',
                'Method', 'Path', 'Status', 'IP', 'Request ID', 'Reason',
            ]);
            foreach ($rows as $event) {
                fputcsv($output, array_map($this->csvCell(...), [
                    $event->event_uuid, $event->occurred_at?->toIso8601String(), $event->category,
                    $event->action, $event->outcome, $event->actor_id, $event->actor_name,
                    $event->actor_email, $event->subject_type, $event->subject_id,
                    $event->event_id, $event->route_name, $event->http_method, $event->path,
                    $event->status_code, $event->ip_address, $event->request_id, $event->reason,
                ]));
            }
            fclose($output);
        }, 'cape-tennis-audit-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:120'],
            'outcome' => ['nullable', 'in:attempted,succeeded,failed,denied'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'event_id' => ['nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', 'string', 'max:120'],
            'subject_id' => ['nullable', 'string', 'max:191'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        return AuditEvent::query()
            ->when($request->filled('from'), fn (Builder $query) => $query->where('occurred_at', '>=', Carbon::parse((string) $request->string('from'))->startOfDay()))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('occurred_at', '<=', Carbon::parse((string) $request->string('to'))->endOfDay()))
            ->when($request->filled('category'), fn (Builder $query) => $query->where('category', (string) $request->string('category')))
            ->when($request->filled('action'), fn (Builder $query) => $query->where('action', (string) $request->string('action')))
            ->when($request->filled('outcome'), fn (Builder $query) => $query->where('outcome', (string) $request->string('outcome')))
            ->when($request->filled('actor_id'), fn (Builder $query) => $query->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('event_id'), fn (Builder $query) => $query->where('event_id', $request->integer('event_id')))
            ->when($request->filled('subject_type'), function (Builder $query) use ($request): void {
                $type = str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->string('subject_type'));
                $query->where('subject_type', 'like', '%'.$type.'%');
            })
            ->when($request->filled('subject_id'), fn (Builder $query) => $query->where('subject_id', (string) $request->string('subject_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->string('search')).'%';
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('action', 'like', $search)
                        ->orWhere('actor_name', 'like', $search)
                        ->orWhere('actor_email', 'like', $search)
                        ->orWhere('subject_label', 'like', $search)
                        ->orWhere('route_name', 'like', $search)
                        ->orWhere('path', 'like', $search)
                        ->orWhere('request_id', 'like', $search);
                });
            });
    }

    private function csvCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
