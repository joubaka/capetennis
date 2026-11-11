<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, User};
use App\Services\{BulkMailDispatcher, CavaliersRosterReport};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CavaliersRosterReportController extends Controller
{
    public function index(Request $request, Event $event, CavaliersRosterReport $reports)
    {
        $reports->authorize($event, $request->user());
        if ($request->expectsJson()) {
            $input = $request->validate(['q' => 'required|string|min:2|max:80']);
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $input['q']).'%';
            return response()->json(['users' => User::select('id', 'name', 'email')->whereNotNull('email')
                ->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("userName LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("userSurname LIKE ? ESCAPE '!'", [$pattern]))
                ->orderBy('name')->limit(20)->get()->filter(fn ($u) => filter_var($u->email, FILTER_VALIDATE_EMAIL))->values()])->header('Cache-Control', 'private, no-store');
        }
        return response()->view('backend.nominations.cavaliers-report-share', compact('event'))->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, Event $event, CavaliersRosterReport $reports)
    {
        $reports->authorize($event, $request->user());
        $input = $request->validate(['recipient_ids' => 'required|array|list|min:1|max:30', 'recipient_ids.*' => 'required|integer|distinct']);
        $users = User::whereIn('id', $input['recipient_ids'])->orderBy('id')->get(['id', 'name', 'email']);
        if ($users->count() !== count($input['recipient_ids']) || $users->contains(fn ($u) => ! filter_var($u->email, FILTER_VALIDATE_EMAIL))
            || $users->map(fn ($u) => strtolower(trim($u->email)))->unique()->count() !== $users->count()) {
            throw ValidationException::withMessages(['recipient_ids' => 'Choose existing accounts with valid, distinct email addresses.']);
        }
        $report = $reports->report($event, $request->user());
        $token = (string) Str::uuid();
        $snapshot = ['event_id' => $event->id, 'created_by' => $request->user()->id, 'expires' => now()->addMinutes(30)->timestamp,
            'users' => $users->toArray(), 'fingerprint' => hash('sha256', json_encode($report, JSON_THROW_ON_ERROR)),
            'subject' => 'Private nomination review: '.$event->name, 'body' => view('emails.cavaliers-roster-report', compact('report'))->render(), 'pdf' => base64_encode($reports->pdf($report))];
        $request->session()->put('cavaliers_report', [$token => $snapshot]);
        return response()->json(['token' => $token, 'html' => $reports->mail($snapshot)->render(), 'recipients' => $users,
            'pdf_url' => route('backend.cavaliers-report.pdf', [$event, $token]), 'send_url' => route('backend.cavaliers-report.send', [$event, $token])])->header('Cache-Control', 'private, no-store');
    }

    private function snapshot(Request $request, Event $event, string $token, CavaliersRosterReport $reports): array
    {
        $reports->authorize($event, $request->user());
        $snapshot = $request->session()->get('cavaliers_report.'.$token);
        abort_unless($snapshot && (int) $snapshot['event_id'] === (int) $event->id && (int) $snapshot['created_by'] === (int) $request->user()->id && $snapshot['expires'] >= now()->timestamp, 409, 'This preview expired. Prepare it again.');
        return $snapshot;
    }

    public function pdf(Request $request, Event $event, string $token, CavaliersRosterReport $reports)
    {
        $snapshot = $this->snapshot($request, $event, $token, $reports);
        abort_unless(hash('sha256', json_encode($reports->report($event, $request->user()), JSON_THROW_ON_ERROR)) === $snapshot['fingerprint'], 409, 'The roster or evidence changed. Prepare the preview again.');
        return response(base64_decode($snapshot['pdf'], true))->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'inline; filename="nomination-review.pdf"')->header('Cache-Control', 'private, no-store');
    }

    public function send(Request $request, Event $event, string $token, CavaliersRosterReport $reports, BulkMailDispatcher $mailer)
    {
        $snapshot = $this->snapshot($request, $event, $token, $reports);
        abort_unless(hash('sha256', json_encode($reports->report($event, $request->user()), JSON_THROW_ON_ERROR)) === $snapshot['fingerprint'], 409, 'The roster or evidence changed. Prepare and review the preview again.');
        foreach ($snapshot['users'] as $user) abort_unless(User::whereKey($user['id'])->where('email', $user['email'])->where('name', $user['name'])->exists(), 409, 'A recipient changed. Prepare the preview again.');
        $result = DB::transaction(function () use ($snapshot, $token, $event, $mailer, $reports) {
            $total = 0;
            foreach ($snapshot['users'] as $user) {
                $payload = collect($snapshot)->except(['users', 'expires'])->all() + ['recipient_user_id' => $user['id'], 'recipient_email' => strtolower(trim($user['email'])), 'campaign_key' => 'cavaliers-report:'.$token, 'recipient_kind' => 'system-user'];
                $payload['report_signature'] = $reports->signature($payload);
                $stats = $mailer->dispatch('cavaliers_roster_report', $event, [$user], $payload, atomicDatabaseQueue: true);
                $total += $stats['queued'];
            }
            return $total;
        });
        return response()->json(['queued' => $result])->header('Cache-Control', 'private, no-store');
    }
}
