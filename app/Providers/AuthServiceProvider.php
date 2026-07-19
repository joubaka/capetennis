<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\TeamTie;
use App\Models\Wallet;
use App\Policies\DrawPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\TeamDrawPolicy;
use App\Policies\WalletPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        CategoryEventRegistration::class => RegistrationPolicy::class,
        Draw::class => DrawPolicy::class,
        TeamTie::class => TeamDrawPolicy::class,
        Wallet::class => WalletPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Implicitly grant "Super-Admin" role all permission checks using can()
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super-user')) {
                return true;
            }
        });

        // ── Team-Draw abilities ────────────────────────────────────────────
        // Event-scoped and Draw-scoped abilities are defined here because
        // TeamDrawPolicy is only registered as the model policy for TeamTie.
        // Tie-scoped abilities (validateTie, publishTie, generateRubbersForTie)
        // are resolved automatically via the TeamTie → TeamDrawPolicy mapping.

        $teamDrawPolicy = static fn () => new TeamDrawPolicy();

        Gate::define('team-draw.viewFormats', function ($user, \App\Models\Event $event) use ($teamDrawPolicy) {
            return $teamDrawPolicy()->viewFormats($user, $event);
        });

        Gate::define('team-draw.createFormat', function ($user, \App\Models\Event $event) use ($teamDrawPolicy) {
            return $teamDrawPolicy()->createFormat($user, $event);
        });

        Gate::define('team-draw.updateTeamDraw', function ($user, \App\Models\Draw $draw) use ($teamDrawPolicy) {
            return $teamDrawPolicy()->updateTeamDraw($user, $draw);
        });

        Gate::define('team-draw.generateTies', function ($user, \App\Models\Draw $draw) use ($teamDrawPolicy) {
            return $teamDrawPolicy()->generateTies($user, $draw);
        });

        Gate::define('team-draw.generateRubbers', function ($user, \App\Models\Draw $draw) use ($teamDrawPolicy) {
            return $teamDrawPolicy()->generateRubbers($user, $draw);
        });

        Gate::define('team-draw.regenerate', function ($user, \App\Models\Draw $draw) use ($teamDrawPolicy) {
            return $teamDrawPolicy()->regenerate($user, $draw);
        });

        // ── Individual-draw abilities ──────────────────────────────────────────
        // Create an individual (non-team) draw for an event.
        Gate::define('individual-draw.create', function ($user, \App\Models\Event $event) {
            return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
        });

        // View / print draw data for any draw belonging to an event.
        Gate::define('event-draw.view', function ($user, \App\Models\Event $event) {
            return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
        });

        // Create a new draw (individual) for an event.
        Gate::define('draw.create', function ($user, \App\Models\Event $event) {
            return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
        });
    }
}
