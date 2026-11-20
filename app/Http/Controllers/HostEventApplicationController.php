<?php

namespace App\Http\Controllers;

use App\Jobs\SendHostEventApplicationNotification;
use App\Models\{HostEventApplication, HostEventNotification, User};
use App\Services\Marketing\PanarottisCalculator;
use App\Services\Marketing\HostCourtCapacityPlanner;
use App\Services\Marketing\HostProposalProjection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HostEventApplicationController extends Controller
{
    public function landing()
    {
        return view('frontend.host-events.landing');
    }

    public function create(Request $request)
    {
        $input = $request->old();
        $tokens = array_filter($request->session()->get('host_application_tokens', []), fn ($entry) => is_array($entry)
            && ($entry['issued_at'] ?? 0) >= now()->subDay()->timestamp && ($entry['user_id'] ?? null) === $request->user()->id);
        $candidateToken = $input['submission_token'] ?? null;
        $token = is_string($candidateToken) && isset($tokens[$candidateToken]) ? $candidateToken : (string) Str::uuid();
        if (!isset($tokens[$token])) $tokens[$token] = ['issued_at' => now()->timestamp, 'user_id' => $request->user()->id];
        $tokens = array_slice($tokens, -10, null, true);
        $request->session()->put('host_application_tokens', $tokens);
        try {
            $plan = app(HostCourtCapacityPlanner::class)->calculate(array_replace(app(HostCourtCapacityPlanner::class)->defaults(), $input));
        } catch (\Illuminate\Validation\ValidationException) { $plan = null; }
        return $this->form($token, $input, $plan);
    }

    public function suggestions(Request $request, HostCourtCapacityPlanner $planner)
    {
        $request->validate(['submission_token' => 'required|uuid', 'use_suggested_limits' => 'sometimes|boolean']);
        $this->assertToken($request, $request->input('submission_token'));
        $plan = $planner->calculate($request->all());
        $plan['financial_projection'] = app(HostProposalProjection::class)->calculate($plan, $request->all(), $request->boolean('use_suggested_limits', true));
        return response()->json($plan);
    }

    private function form(string $token, array $input, ?array $plan)
    {
        $projection = null;
        if ($plan) {
            try { $projection = app(HostProposalProjection::class)->calculate($plan, $input); }
            catch (\Illuminate\Validation\ValidationException) { /* Keep invalid inputs editable; never show stale totals. */ }
        }
        return view('frontend.host-events.create', ['token' => $token, 'input' => $input, 'plan' => $plan,
            'projection' => $projection, 'projectionDefaults' => app(HostProposalProjection::class)->defaults(),
            'capacityDefaults' => app(HostCourtCapacityPlanner::class)->defaults(), 'defaults' => app(PanarottisCalculator::class)->defaults()]);
    }

    private function assertToken(Request $request, string $token): void
    {
        $issued = $request->session()->get('host_application_tokens.'.$token);
        abort_unless(is_array($issued) && ($issued['issued_at'] ?? 0) >= now()->subDay()->timestamp
            && ($issued['user_id'] ?? null) === $request->user()->id, 419, 'This proposal form expired. Open a new form.');
    }

    public function store(Request $request)
    {
        try {
            return $this->submit($request);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception->redirectTo(route('host-events.create'));
        }
    }

    private function submit(Request $request)
    {
        $data = $request->validate([
            'submission_token' => 'required|uuid', 'event_name' => 'required|string|max:255',
            'academy' => 'required|string|max:191', 'contact_name' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255', 'phone' => ['required', 'string', 'max:40', 'regex:/^[+0-9() .-]+$/'],
            'town' => 'required|string|max:255', 'venue' => 'required|string|max:255',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date', 'courts' => 'required|integer|between:1,100',
            'limits' => 'required|array|size:4', 'limits.*' => 'required|integer|between:0,1600',
            'fees' => 'required|array|size:4', 'fees.*' => ['required', 'numeric', 'between:0,100000', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],
            'notes' => 'nullable|string|max:10000',
        ]);
        $this->assertToken($request, $data['submission_token']);
        $plan = app(HostCourtCapacityPlanner::class)->calculate($request->all());
        abort_unless(array_keys($data['limits']) === [0, 1, 2, 3] && array_keys($data['fees']) === [0, 1, 2, 3], 422);
        if (array_sum($data['limits']) < 1) return redirect()->route('host-events.create')->withErrors(['limits' => 'Include at least one place in your proposed categories.'])->withInput();
        foreach ($data['limits'] as $i => $limit) {
            if ($limit % 4 !== 0 || $limit > $plan['limits'][$i]) {
                return redirect()->route('host-events.create')->withErrors(['limits.'.$i => 'Use whole four-player groups, no higher than the calculated suggestion.'])->withInput();
            }
        }
        $plan['financial_projection'] = app(HostProposalProjection::class)->calculate($plan, $request->all());
        $data['email'] = mb_strtolower(trim($data['email']));
        $application = DB::transaction(function () use ($data, $request, $plan) {
            // Serialise one applicant's retries, including simultaneous browser submissions.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = HostEventApplication::where('submission_token', $data['submission_token'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $request->user()->id, 403);
                return $existing;
            }
            $categories = [];
            foreach (PanarottisCalculator::CATEGORIES as $i => $name) {
                $categories[] = ['name' => $name, 'limit' => (int) $data['limits'][$i], 'fee' => (string) $data['fees'][$i]];
            }
            unset($data['limits'], $data['fees']);
            $application = HostEventApplication::create($data + ['user_id' => $request->user()->id, 'status' => 'pending', 'requested_categories' => $categories, 'court_plan' => $plan]);
            User::whereHas('roles', fn ($q) => $q->where('name', 'super-user')->where('guard_name', 'web'))->select('users.id')->chunkById(100, function ($admins) use ($application) {
                foreach ($admins as $admin) {
                    $notification = HostEventNotification::create(['application_id' => $application->id, 'admin_id' => $admin->id, 'status' => 'queued']);
                    DB::afterCommit(function () use ($notification) {
                        try {
                            \Illuminate\Support\Facades\Bus::dispatch((new SendHostEventApplicationNotification($notification->id))->onConnection('database')->afterCommit());
                        } catch (\Throwable) {
                            // The proposal is already saved. Do not retry a possibly accepted queue submission automatically.
                            HostEventNotification::whereKey($notification->id)->where('status', 'queued')->update(['status' => 'needs_review']);
                        }
                    });
                }
            });
            return $application;
        });
        return redirect()->route('host-events.show', $application)->with('success', 'Your proposal has been submitted for Cape Tennis review. It is not yet approved or published.');
    }

    public function show(Request $request, HostEventApplication $application)
    {
        abort_unless((int) $application->user_id === (int) $request->user()->id || $request->user()->hasRole('super-user'), 403);
        return view('frontend.host-events.show', compact('application'));
    }
}
