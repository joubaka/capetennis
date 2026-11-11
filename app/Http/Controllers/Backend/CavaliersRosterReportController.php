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
        $subject = 'Private nomination review: '.$event->name;
        $message = "Please review the attached nomination report for {$event->name}.\n\nIt includes the selected players, up to five reserves, the next five candidates, and explanations for Inter-Districts players outside the saved top 32.\n\nPlease keep this review copy within the selected group.\n\nKind regards,\nCape Tennis";
        $fromName = config('mail.from.name', 'Cape Tennis');
        $fromAddress = mb_strtolower(config('mail.from.address'));
        $replyTo = $fromAddress;
        $fromAddresses = app(\App\Services\InvitationMailSecurity::class)->allowedFromAddresses();
        return response()->view('backend.nominations.cavaliers-report-share', compact('event', 'subject', 'message', 'fromName', 'fromAddress', 'replyTo', 'fromAddresses'))->header('Cache-Control', 'private, no-store');
    }

    private function compositionRules(): array
    {
        return ['subject' => ['required', 'string', 'max:200', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'message' => ['required', 'string', 'max:10000', 'not_regex:/\x00/u'],
            'from_name' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'from_address' => ['required', 'string', 'max:254', 'email:rfc', 'not_regex:/[\x00-\x1F\x7F]/u'],
            'reply_to' => ['required', 'string', 'max:254', 'email:rfc', 'not_regex:/[\x00-\x1F\x7F]/u']];
    }

    private function normalizeComposition(array $input): array
    {
        foreach (['from_address', 'reply_to'] as $key) $input[$key] = mb_strtolower($input[$key]);
        app(\App\Services\InvitationMailSecurity::class)->assertAllowedFrom($input['from_address']);
        return $input;
    }

    public function preview(Request $request, Event $event, CavaliersRosterReport $reports)
    {
        $reports->authorize($event, $request->user());
        $input = $this->normalizeComposition($request->validate(['recipient_ids' => 'required|array|list|min:1|max:30', 'recipient_ids.*' => 'required|integer|distinct', ...$this->compositionRules()]));
        $users = User::whereIn('id', $input['recipient_ids'])->orderBy('id')->get(['id', 'name', 'email']);
        if ($users->count() !== count($input['recipient_ids']) || $users->contains(fn ($u) => ! filter_var($u->email, FILTER_VALIDATE_EMAIL))
            || $users->map(fn ($u) => strtolower(trim($u->email)))->unique()->count() !== $users->count()) {
            throw ValidationException::withMessages(['recipient_ids' => 'Choose existing accounts with valid, distinct email addresses.']);
        }
        $report = $reports->report($event, $request->user());
        $token = (string) Str::uuid();
        $snapshot = ['event_id' => $event->id, 'created_by' => $request->user()->id, 'expires' => now()->addMinutes(30)->timestamp,
            'users' => $users->toArray(), 'fingerprint' => hash('sha256', json_encode($report, JSON_THROW_ON_ERROR)),
            'subject' => $input['subject'], 'message' => $input['message'],
            'from_name' => $input['from_name'], 'from_address' => $input['from_address'], 'reply_to' => $input['reply_to'],
            'body' => view('emails.cavaliers-roster-report', ['messageText' => $input['message']])->render(), 'pdf' => base64_encode($reports->pdf($report))];
        $request->session()->put('cavaliers_report', [$token => $snapshot]);
        return response()->json(['token' => $token, ...collect($snapshot)->only(array_keys($this->compositionRules()))->all(), 'html' => $reports->mail($snapshot)->render(), 'recipients' => $users,
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
        $input = $this->normalizeComposition($request->validate($this->compositionRules()));
        foreach (array_keys($this->compositionRules()) as $field) abort_unless($input[$field] === ($snapshot[$field] ?? null), 409, 'The email or sender changed. Prepare and review the preview again.');
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
