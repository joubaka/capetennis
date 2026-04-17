<?php

namespace App\Http\Controllers\backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\User;
use Illuminate\Http\Request;

class ConvenorController extends Controller
{
    public function index()
    {
       return view('backend.convenor.index-convenor');
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id'  => 'required|exists:users,id',
        ]);

        $exists = EventConvenor::where('event_id', $request->event_id)
            ->where('user_id', $request->user_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'User is already a convenor for this event.'], 422);
        }

        EventConvenor::create([
            'event_id' => $request->event_id,
            'user_id'  => $request->user_id,
        ]);

        return response()->json(['message' => 'Convenor added successfully.']);
    }

    public function show($id)
    {
        $data['event'] = Event::find($id);
        $data['convenors'] = EventConvenor::where('event_id',$id)->with('user')->get();
        return view('backend.convenor.index-convenor',$data);
    }

    public function edit($id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        $convenor = EventConvenor::findOrFail($id);
        $convenor->delete();

        return response()->json(['message' => 'Convenor removed successfully.']);
    }

    /**
     * Search users for Select2 AJAX dropdown.
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');

        $users = User::where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json($users->map(function ($u) {
            return ['id' => $u->id, 'text' => $u->name . ' (' . $u->email . ')'];
        }));
    }
}
