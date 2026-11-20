<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\HostEventApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HostEventReviewController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])]]);
        $status = $data['status'] ?? 'pending';
        $applications = HostEventApplication::query()->when($status !== 'all', fn ($q) => $q->where('status', $status))->with('event:id,name,published')->latest('id')->paginate(20)->withQueryString();
        return view('backend.marketing.applications', compact('applications', 'status'));
    }

    public function show(HostEventApplication $application)
    {
        $application->load('event');
        $notificationCounts = $application->notifications()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        return view('backend.marketing.application-review', compact('application', 'notificationCounts'));
    }

    public function review(Request $request, HostEventApplication $application)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'review_notes' => 'required|string|max:10000', 'confirm' => 'accepted']);
        DB::transaction(function () use ($application, $data, $request) {
            $application = HostEventApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->status === $data['decision']) return;
            abort_unless($application->status === 'pending' && !$application->event_id, 409, 'This proposal has already been reviewed. Reload to see its current state.');
            $application->update(['status' => $data['decision'], 'review_notes' => $data['review_notes'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });
        return redirect()->route('superadmin.marketing.applications.show', $application)->with('success', 'Review saved. No event has been published.');
    }
}
