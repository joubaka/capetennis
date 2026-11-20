<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Camps\{CampAdmission, CampInvitations, CampWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CampController extends Controller
{
    public function coach(string $token, CampWorkflow $workflow)
    {
        $link = $workflow->coachByToken($token);
        $config = app(CampAdmission::class)->configuration($link->event_id);
        $requestToken = (string) Str::uuid();
        return view('frontend.camp.coach', compact('link', 'config', 'token', 'requestToken'));
    }

    public function submit(Request $request, string $token, CampWorkflow $workflow)
    {
        $data = $request->validate(['request_token' => 'required|uuid', 'name' => 'required|string|max:100', 'surname' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today', 'gender' => 'required|integer|in:1,2', 'guardian_name' => 'required|string|max:255',
            'guardian_email' => 'required|email|max:255', 'reason' => 'nullable|string|max:2000']);
        $workflow->submitCoach($token, $data);
        return back()->with('status', 'Nomination submitted for organiser review. An invitation will follow only if approved.');
    }

    public function invitation(string $token, CampInvitations $invitations)
    {
        $nominee = $invitations->token($token);
        abort_unless($nominee->event->published, 404);
        $participation = $invitations->participation($nominee);
        return view('frontend.camp.invitation', compact('nominee', 'participation', 'token'));
    }

    public function decline(Request $request, string $token, CampInvitations $invitations)
    {
        $request->validate(['confirmed' => 'accepted']);
        $invitations->decline($request->user(), $token);
        return back()->with('status', 'Camp invitation declined.');
    }
}
