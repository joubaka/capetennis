<?php

namespace App\Services;

use App\Models\{BulkEmailLog, CategoryEvent, Event, Player, User};
use App\Support\EventTypePresentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

final class CavaliersRosterReport
{
    public function explanationGroups(array $candidates, array $roster): array
    {
        $selected = $roster['selected'] ?? [];
        $reserves = $roster['reserves'] ?? [];
        $declined = $roster['declined'] ?? [];
        $selectedIndexes = collect($candidates)->keys()->filter(fn ($index) => in_array($candidates[$index]['id'], $selected, true));
        $rows = collect($candidates)->map(function (array $candidate, int $index) use ($selectedIndexes, $reserves, $declined) {
            $numbers = collect($candidate['matches'] ?? [])->pluck('playing_number')->filter(fn ($number) => is_int($number) && $number > 0)->unique()->sort()->values()->all();
            $badge = ($candidate['lost_all'] ?? false) ? 'Lost all matches'
                : (($candidate['only_beat_winless'] ?? false) ? 'Only beat winless players' : 'Selector reason required');
            if (in_array($candidate['id'], $declined, true)) $badge = 'Declined';
            $reserve = array_search($candidate['id'], $reserves, true);
            $status = in_array($candidate['id'], $declined, true) ? 'Recorded as declined.' : ($reserve !== false ? 'Saved reserve #'.($reserve + 1).'.' : 'Outside the saved top 32.');
            $laterSelected = $selectedIndexes->filter(fn ($selectedIndex) => $selectedIndex > $index)->count();
            $note = $laterSelected ? 'Selection review needed: ahead of '.$laterSelected.' saved selected player(s) in review order. Selector reason required.' : '';
            return ['id' => $candidate['id'], 'name' => $candidate['name'], 'evidence' => $candidate,
                'playing_numbers' => $numbers, 'badge' => $badge, 'source_order' => $index + 1, 'selection_status' => $status,
                'review_note' => in_array($candidate['id'], $declined, true) ? 'The saved decline explains absence from the top 32.' : $note];
        });
        $next = $rows->whereNotIn('id', [...$selected, ...$reserves, ...$declined])->take(5)->values();
        $interDistricts = $rows->filter(fn ($row) => ($row['evidence']['source'] ?? null) === 'Inter-Districts')
            ->whereNotIn('id', [...$selected, ...$next->pluck('id')->all()])->values();
        return ['next_candidates' => $next->all(), 'inter_districts' => $interDistricts->all()];
    }

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
            $sections[] = ['division' => $division->category->name, 'revision' => $draft->revision, 'preview' => $preview, 'rows' => $rows,
                'explanations' => $this->explanationGroups($preview['candidates'], $roster)];
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
        return (new \App\Mail\BulkEventMail($snapshot['subject'], $snapshot['body'], $snapshot['from_name'] ?? 'Cape Tennis', $snapshot['reply_to'] ?? config('mail.from.address'), $snapshot['from_address'] ?? config('mail.from.address')))
            ->attachData(base64_decode($snapshot['pdf'], true), 'nomination-review.pdf', ['mime' => 'application/pdf']);
    }

    public function authorizedMailable(BulkEmailLog $log): ?\App\Mail\BulkEventMail
    {
        $payload = $log->payload;
        $signature = $payload['report_signature'] ?? '';
        unset($payload['report_signature'], $payload['queue_state']);
        if (! hash_equals($this->signature($payload), $signature)) return null;
        if (! in_array(mb_strtolower(trim($payload['from_address'] ?? config('mail.from.address'))), app(InvitationMailSecurity::class)->allowedFromAddresses(), true)) return null;
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
