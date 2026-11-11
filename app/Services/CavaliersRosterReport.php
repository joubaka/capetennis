<?php

namespace App\Services;

use App\Models\{BulkEmailLog, CategoryEvent, Event, Player, User};
use App\Support\EventTypePresentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

final class CavaliersRosterReport
{
    public function signature(array $payload): string
    {
        return hash_hmac('sha256', json_encode(\Illuminate\Support\Arr::sortRecursive($payload), JSON_THROW_ON_ERROR), config('app.key'));
    }
    public function authorize(Event $event, User $actor): void
    {
        abort_unless((int) $event->id === CavaliersTrialCandidates::TRIALS_EVENT && EventTypePresentation::template($event) === 'cavaliers_trials', 404);
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
    }

    public function report(Event $event, User $actor): array
    {
        $this->authorize($event, $actor);
        $divisions = CategoryEvent::where('event_id', $event->id)->with('category')->orderBy('id')->get();
        $drafts = DB::table('cavaliers_trial_roster_drafts')->where('event_id', $event->id)->get()->keyBy('category_event_id');
        $sections = [];
        foreach ($divisions as $division) {
            $draft = $drafts->get($division->id);
            if (! $draft) continue;
            $preview = app(CavaliersTrialCandidates::class)->preview($event, $division, $actor);
            $candidates = collect($preview['candidates'])->keyBy('id');
            $roster = [];
            foreach (['selected', 'reserves', 'declined'] as $key) $roster[$key] = array_map('intval', json_decode($draft->$key ?? '[]', true, flags: JSON_THROW_ON_ERROR));
            $names = Player::whereIn('id', array_merge(...array_values($roster)))->get(['id', 'name', 'surname'])->keyBy('id');
            $rows = [];
            foreach ($roster as $status => $ids) foreach ($ids as $index => $id) {
                abort_unless($names->has($id), 422, 'Remove unavailable players from the saved roster before sharing.');
                $rows[] = ['id' => $id, 'name' => $names[$id]->full_name, 'status' => $status, 'position' => $index + 1, 'evidence' => $candidates->get($id)];
            }
            foreach ($candidates->except(array_merge(...array_values($roster))) as $candidate) $rows[] = ['id' => $candidate['id'], 'name' => $candidate['name'], 'status' => 'omitted', 'position' => null, 'evidence' => $candidate];
            $sections[] = ['division' => $division->category->name, 'revision' => $draft->revision, 'preview' => $preview, 'rows' => $rows];
        }
        abort_unless($sections, 422, 'Save at least one division roster before preparing a report.');
        return ['event_id' => $event->id, 'event_name' => $event->name, 'sections' => $sections,
            'unsaved_divisions' => $divisions->reject(fn ($division) => $drafts->has($division->id))->map(fn ($division) => $division->category->name)->values()->all()];
    }

    public function pdf(array $report): string
    {
        return Pdf::loadView('backend.nominations.cavaliers-report-pdf', compact('report'))->setPaper('a4')->output();
    }

    public function mail(array $snapshot): \App\Mail\BulkEventMail
    {
        return (new \App\Mail\BulkEventMail($snapshot['subject'], $snapshot['body'], 'Cape Tennis', config('mail.from.address')))
            ->attachData(base64_decode($snapshot['pdf'], true), 'nomination-review.pdf', ['mime' => 'application/pdf']);
    }

    public function authorizedMailable(BulkEmailLog $log): ?\App\Mail\BulkEventMail
    {
        $payload = $log->payload;
        $signature = $payload['report_signature'] ?? '';
        unset($payload['report_signature'], $payload['queue_state']);
        if (! hash_equals($this->signature($payload), $signature)) return null;
        $event = Event::find($payload['event_id'] ?? 0);
        $actor = User::find($payload['created_by'] ?? 0);
        $recipient = User::find($payload['recipient_user_id'] ?? 0);
        if ($log->mail_type !== 'cavaliers_roster_report' || ! $event || ! $actor || ! $recipient || $log->related_type !== Event::class || (int) $log->related_id !== (int) $event->id
            || strtolower(trim($recipient->email)) !== ($payload['recipient_email'] ?? null)
            || strtolower(trim($log->recipient_email)) !== ($payload['recipient_email'] ?? null)) return null;
        try {
            $this->authorize($event, $actor);
            if (hash('sha256', json_encode($this->report($event, $actor), JSON_THROW_ON_ERROR)) !== $payload['fingerprint']) return null;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) { return null; }
        return $this->mail($payload)->with('cavaliers_report_log_id', $log->id);
    }
}
