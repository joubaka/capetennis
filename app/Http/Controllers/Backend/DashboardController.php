<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Player;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
  public function dashboard()
  {
    $user = Auth::user()->load([
      'wallet.transactions',
      'players',
      'events',
    ]);

    $adminEvents = Event::whereHas('admins', function ($query) use ($user) {
      $query->where('user_id', $user->id);
    })->get();

    $players = Player::select('id', 'name', 'surname', 'email')->get();

    return view('backend.dashboard', compact(
      'user',
      'players',
      'adminEvents'
    ));
  }
}
