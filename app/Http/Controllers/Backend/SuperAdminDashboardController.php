<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;

class SuperAdminDashboardController extends Controller
{
  public function index()
  {
    $totalUsers        = User::count();
    $totalPlayers      = Player::count();
    $totalEvents       = Event::count();
    $activeEvents      = Event::where('start_date', '<=', Carbon::today())
                              ->where('end_date', '>=', Carbon::today())
                              ->count();
    $totalRegistrations = Registration::count();

    return view('backend.superAdminDashboard', compact(
      'totalUsers',
      'totalPlayers',
      'totalEvents',
      'activeEvents',
      'totalRegistrations'
    ));
  }
}
