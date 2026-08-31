<?php

namespace App\Http\Controllers\Backend;

use App\Models\Event;
use App\Http\Controllers\Controller;
use App\Models\MastersInvitationBatch;
use App\Models\MastersInvitation;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Http\Request;
use App\Models\MastersRankingCategoryLink;
use Illuminate\Support\Facades\Log;

class MastersInvitationController extends Controller
{
    public function setup(Event $event)
    {
        $this->authorizeEvent($event);
        abort_unless($event->isMasters(), 404);

        $event->load(['series', 'categoryEvents.category']);
        $rankingCategoryLinks = MastersRankingCategoryLink::with(['rankingList.category', 'categoryEvent.category'])
            ->where('event_id', $event->id)->get();
        $rankingLists = $event->series?->ranking_lists()->with('category')->get() ?? collect();
        $publishedRuns = $event->series
            ? \App\Models\SeriesRanking::where('series_id', $event->series_id)
                ->where('status', 'published')->whereNotNull('run_id')->select('run_id')
                ->distinct()->orderByDesc('run_id')->pluck('run_id')
            : collect();

        return view('backend.event.masters.setup', compact(
            'event', 'rankingLists', 'publishedRuns', 'rankingCategoryLinks'
        ));
    }

    public function syncCategories(Event $event, MastersInvitationService $service)
    {
        $this->authorizeEvent($event);
        $links = $service->syncRankingCategories($event);
        Log::info('Masters ranking category sync request finished', [
            'event_id' => $event->id,
            'user_id' => auth()->id(),
            'synced_count' => count($links),
        ]);
        return back()->with('success', count($links)
            ? 'Masters categories synced from the Series ranking lists.'
            : 'No Masters categories were synced. Check that this event is linked to a series with ranking lists.');
    }

    public function updateCategories(Request $request, Event $event, MastersInvitationService $service)
    {
        $this->authorizeEvent($event);
        $data = $request->validate(['links' => ['required', 'array'], 'links.*.enabled' => ['nullable', 'boolean'], 'links.*.top_x' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $service->updateRankingCategoryLinks($event, $data['links']);
        return back()->with('success', 'Masters ranking-category settings saved.');
    }

    public function updateCategory(Request $request, Event $event, MastersRankingCategoryLink $link, MastersInvitationService $service)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'top_x' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $updated = $service->updateRankingCategoryLink(
            $event,
            $link,
            array_key_exists('enabled', $data) ? (bool) $data['enabled'] : null,
            array_key_exists('top_x', $data) ? (int) $data['top_x'] : null,
        );

        return response()->json([
            'ok' => true,
            'enabled' => (bool) $updated->enabled,
            'top_x' => (int) $updated->top_x,
            'message' => 'Category settings updated.',
        ]);
    }

    public function generate(Request $request, Event $event, MastersInvitationService $service)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'series_id' => ['required', 'integer'], 'ranking_run_id' => ['required', 'string', 'max:64'],
            'top_x' => ['required', 'integer', 'min:1', 'max:100'], 'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.ranking_list_id' => ['required', 'integer'], 'mappings.*.category_event_id' => ['required', 'integer'],
            'response_deadline' => ['nullable', 'date'], 'payment_deadline' => ['nullable', 'date'],
            'replacement_payment_deadline' => ['nullable', 'date'],
        ]);
        $batch = $service->generateBatch($event, (int) $data['series_id'], $data['ranking_run_id'], $data['mappings'], (int) $data['top_x'], $request->user(), $data);
        return redirect()->route('backend.masters.show', $batch)->with('success', 'Invitation batch generated. Confirm the dates before sending invitations.');
    }
    public function show(MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $batch->load(['event', 'series', 'invitations.player.user', 'invitations.player.users', 'invitations.categoryEvent.category']);
        $readiness = $service->readiness($batch);
        return view('backend.masters.show', compact('batch', 'readiness'));
    }

    public function review(MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $batch->load(['event', 'series', 'invitations.player.user', 'invitations.player.users', 'invitations.categoryEvent.category']);
        abort_unless($batch->response_deadline && $batch->payment_deadline && $batch->replacement_payment_deadline, 422, 'Save invitation details before opening the review screen.');
        return view('backend.masters.review', compact('batch'));
    }

    public function previewInvitation(MastersInvitation $invitation)
    {
        $this->authorizeBatch($invitation->batch);
        $invitation->load(['batch.event', 'categoryEvent.category', 'player']);
        $mail = new \App\Mail\MastersInvitationMail($invitation);

        return view('backend.masters.invitation-preview', [
            'subject' => $mail->subject(),
            'body' => view('emails.masters.invitation', compact('invitation'))->render(),
        ]);
    }

    public function removeRankingList(Request $request, MastersInvitationBatch $batch, int $rankingListId, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $count = $service->removeRankingListFromBatch($batch, $rankingListId, $request->user());
        return back()->with('success', "Ranking list removed from this batch. {$count} invitation records were removed and it can be generated again.");
    }

    public function restart(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $eventId = $batch->event_id;
        $service->restartBatch($batch, $request->user());
        return redirect()->route('admin.events.overview', $eventId)->with('success', 'Masters invitation batch restarted. Review the rankings and generate a new batch.');
    }

    public function restartPage(MastersInvitationBatch $batch)
    {
        $this->authorizeBatch($batch);
        return redirect()->route('admin.events.overview', $batch->event_id)
            ->with('warning', 'This batch has already been restarted. Generate a new invitation batch from the event overview.');
    }

    public function updateDetails(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $data = $request->validate([
            'response_deadline' => ['required', 'date'],
            'payment_deadline' => ['required', 'date'],
            'replacement_payment_deadline' => ['required', 'date'],
        ]);
        $service->updateBatchDetails($batch, $data);
        return redirect()->route('backend.masters.review', $batch)->with('success', 'Invitation details saved. Review the invitees, then send the invitations.');
    }

    public function sendInvitations(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $count = $service->sendInvitations($batch);
        return redirect()->route('admin.events.overview', $batch->event_id)
            ->with('success', "{$count} invitations have been queued for sending. The Masters event dashboard is now the main operating page.");
    }

    public function publishNamesOnly(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $service->publishNamesOnly($batch, $request->user());

        return redirect()->route('admin.events.overview', $batch->event_id)
            ->with('success', 'Player names are now published publicly. No invitation emails were sent.');
    }

    public function togglePublicList(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $published = $request->boolean('published');
        $service->setPublicListPublished($batch, $published, $request->user());
        return back()->with('success', $published
            ? 'Masters player list published on the public event page.'
            : 'Masters player list unpublished from the public event page.');
    }

    public function toggleRegistration(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $open = $request->boolean('open');
        $service->setRegistrationOpen($batch, $open, $request->user());

        return back()->with('success', $open
            ? 'Masters registration is now open.'
            : 'Masters registration is now closed.');
    }

    public function updateInvitation(Request $request, MastersInvitation $invitation, MastersInvitationService $service)
    {
        $this->authorizeBatch($invitation->batch);
        $data = $request->validate(['status' => ['required', 'in:invited,reserve']]);
        $updated = $service->updateInvitationWave($invitation, $data['status'], $request->user());
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $updated->status, 'message' => $data['status'] === MastersInvitation::INVITED ? 'Player added to the invitation wave.' : 'Player moved to reserve.']);
        }
        return back()->with('success', $data['status'] === MastersInvitation::INVITED ? 'Player added to the invitation wave.' : 'Player moved to reserve.');
    }

    public function removeInvitation(Request $request, MastersInvitation $invitation, MastersInvitationService $service)
    {
        $this->authorizeBatch($invitation->batch);

        if ($invitation->status === MastersInvitation::PAID_CONFIRMED && $invitation->registration_id) {
            $registration = \App\Models\CategoryEventRegistration::with(['categoryEvent.event', 'players', 'user'])
                ->findOrFail($invitation->registration_id);
            $event = $registration->categoryEvent?->event;
            abort_unless($event && $event->id === $invitation->event_id, 404);

            app(\App\Domain\Entries\Services\EntryService::class)->withdrawEntryAsAdmin($registration, $request->user());
            $registration->sendWithdrawalEmails('admin');
            $service->handlePaidWithdrawal((int) $registration->id, $request->user());

            if ($event->canWithdraw()) {
                return redirect()->route('admin.registration.refund.choose', [$event, $registration])
                    ->with('success', 'Paid player removed by admin. Choose whether a refund should be issued.');
            }

            return back()->with('success', 'Paid player removed by admin and deactivated (no refund — withdrawal deadline passed).');
        }
        $service->removeByAdmin($invitation, $request->user());
        return back()->with('success', 'Player removed by admin and retained in the audit history.');
    }

    public function readiness(MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        return response()->json($service->readiness($batch));
    }

    public function toggleAutoReplacement(Request $request, MastersInvitationBatch $batch, MastersInvitationService $service)
    {
        $this->authorizeBatch($batch);
        $enabled = $request->boolean('enabled');
        if ($enabled) {
            $readiness = $service->readiness($batch);
            abort_if($readiness['status'] === 'blocked', 422, 'Masters auto-replacement readiness is blocked.');
        }
        $batch->update(['auto_replacement_enabled' => $enabled]);
        activity('masters')
            ->performedOn($batch)
            ->causedBy($request->user())
            ->withProperties(['enabled' => $enabled, 'readiness' => $enabled ? $service->readiness($batch) : null])
            ->log($enabled ? 'Masters automatic replacement enabled' : 'Masters automatic replacement disabled');
        return back()->with('success', $enabled ? 'Automatic replacement enabled.' : 'Automatic replacement disabled.');
    }

    private function authorizeEvent(Event $event): void
    {
        $user = request()->user();
        abort_unless($user && ($user->hasRole('super-user') || ($user->hasRole('admin') && $user->is_event_admin($event->id))), 403);
    }

    private function authorizeBatch(MastersInvitationBatch $batch): void
    {
        $batch->loadMissing('event');
        $this->authorizeEvent($batch->event);
        abort_unless($batch->event?->isMasters(), 404);
    }
}
