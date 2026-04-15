<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerAgreement;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SuperAdminController extends Controller
{
    /**
     * Show the Super Admin Dashboard.
     */
    public function index()
    {
        // Overview statistics
        $stats = [
            'total_users' => User::count(),
            'total_players' => Player::count(),
            'total_events' => Event::count(),
            'active_events' => Event::where('status', 1)->count(),
            'total_registrations' => Registration::count(),
        ];

        // Agreement statistics
        $activeAgreement = Agreement::where('is_active', 1)->latest()->first();
        $agreementStats = [
            'total_agreements' => Agreement::count(),
            'active_agreement' => $activeAgreement,
            'total_acceptances' => $activeAgreement ? PlayerAgreement::where('agreement_id', $activeAgreement->id)->count() : 0,
            'pending_players' => $activeAgreement ? Player::whereDoesntHave('agreements', function ($q) use ($activeAgreement) {
                $q->where('agreement_id', $activeAgreement->id);
            })->count() : 0,
        ];

        // Profile update statistics
        $oneYearAgo = Carbon::now()->subYear();
        $profileStats = [
            'up_to_date' => Player::where('profile_updated_at', '>=', $oneYearAgo)
                ->where('profile_complete', true)
                ->count(),
            'needs_update' => Player::where(function ($q) use ($oneYearAgo) {
                $q->whereNull('profile_updated_at')
                  ->orWhere('profile_updated_at', '<', $oneYearAgo);
            })->count(),
            'incomplete' => Player::where('profile_complete', false)
                ->orWhereNull('profile_complete')
                ->count(),
            'never_updated' => Player::whereNull('profile_updated_at')->count(),
        ];

        // Recent acceptances
        $recentAcceptances = PlayerAgreement::with(['player', 'agreement'])
            ->orderByDesc('accepted_at')
            ->limit(10)
            ->get();

        // Recent users
        $recentUsers = User::orderByDesc('created_at')
            ->limit(10)
            ->get();

        // All agreements for management
        $agreements = Agreement::orderByDesc('created_at')->get();

        // Players needing attention (outdated or incomplete profiles)
        $playersNeedingAttention = Player::where(function ($q) use ($oneYearAgo) {
            $q->whereNull('profile_updated_at')
              ->orWhere('profile_updated_at', '<', $oneYearAgo)
              ->orWhere('profile_complete', false)
              ->orWhereNull('profile_complete');
        })
        ->orderBy('profile_updated_at', 'asc')
        ->limit(10)
        ->get();

        return view('backend.superadmin.index', compact(
            'stats',
            'agreementStats',
            'profileStats',
            'recentAcceptances',
            'recentUsers',
            'agreements',
            'playersNeedingAttention'
        ));
    }
}
