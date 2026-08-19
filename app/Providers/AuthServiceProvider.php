<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\TeamTie;
use App\Models\Wallet;
use App\Models\Series;
use App\Policies\DrawPolicy;
use App\Policies\RegistrationPolicy;
use App\Policies\TeamDrawPolicy;
use App\Policies\WalletPolicy;
use App\Policies\SeriesPolicy;
use App\Models\DisciplinaryCase;
use App\Policies\DisciplinaryCasePolicy;

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
        Series::class => SeriesPolicy::class,
        DisciplinaryCase::class => DisciplinaryCasePolicy::class,
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
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        // View / print draw data for any draw belonging to an event.
        Gate::define('event-draw.view', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        // Create a new draw (individual) for an event.
        Gate::define('draw.create', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        // Access draw-management pages that have no specific model context.
        Gate::define('draw.admin', function ($user) {
            return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
        });

        // ── Event management ────────────────────────────────────────────────
        Gate::define('event.create', function ($user) {
            return $user->hasAnyRole(['super-user', 'admin']);
        });

        Gate::define('event.manage', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-category.manage', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id);
        });

        // ── Team-Fixture abilities ─────────────────────────────────────────────
        // All team-fixture gates resolve authorization through the fixture's draw
        // using the same event-admin + team-event scope as TeamDrawPolicy.

        /** @param \App\Models\TeamFixture|\App\Models\Draw $subject */
        $resolveTeamFixtureDraw = static function ($subject): ?\App\Models\Draw {
            if ($subject instanceof \App\Models\Draw) {
                return $subject;
            }
            return $subject->draw ?? $subject->draw()->first();
        };

        $canAccessTeamDraw = static function ($user, ?\App\Models\Draw $draw): bool {
            if (!$draw) {
                return false;
            }

            $event = $draw->event()->with('eventTypeModel')->first();
            return $event
                && (int) $event->eventTypeModel?->type === \App\Models\EventType::TEAM
                && ($user->is_event_admin($event->id) || $user->is_convenor($event->id));
        };

        Gate::define('team-fixture.view', function ($user, $subject) use ($resolveTeamFixtureDraw, $canAccessTeamDraw) {
            $draw = $resolveTeamFixtureDraw($subject);
            return $canAccessTeamDraw($user, $draw);
        });

        Gate::define('team-fixture.update', function ($user, $subject) use ($teamDrawPolicy, $resolveTeamFixtureDraw) {
            $draw = $resolveTeamFixtureDraw($subject);
            return $draw ? $teamDrawPolicy()->updateTeamDraw($user, $draw) : false;
        });

        Gate::define('team-fixture.saveScore', function ($user, $subject) use ($teamDrawPolicy, $resolveTeamFixtureDraw) {
            $draw = $resolveTeamFixtureDraw($subject);
            if (!$draw || $draw->locked) {
                return false;
            }

            $event = $draw->event()->with('eventTypeModel')->first();
            if (!$event || (int) $event->eventTypeModel?->type !== \App\Models\EventType::TEAM) {
                return false;
            }

            if ($subject instanceof \App\Models\TeamFixture
                && $subject->teamTie
                && $subject->teamTie->status === \App\Models\TeamTie::STATUS_COMPLETED) {
                return false;
            }

            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('team-fixture.schedule', function ($user, $subject) use ($resolveTeamFixtureDraw, $canAccessTeamDraw) {
            $draw = $resolveTeamFixtureDraw($subject);
            if (!$draw) {
                return false;
            }
            // Schedule changes blocked when the draw is locked
            if ($draw->locked) {
                return false;
            }
            return $canAccessTeamDraw($user, $draw);
        });

        // ── Team-Schedule abilities (TeamScheduleController) ──────────────────
        // Draw-scoped: resolve the draw and delegate to TeamDrawPolicy.
        // Event-scoped: require event-admin or higher role.

        $resolveTeamScheduleDraw = static function ($subject): ?\App\Models\Draw {
            if ($subject instanceof \App\Models\Draw) {
                return $subject;
            }
            return null;
        };

        Gate::define('team-schedule.view', function ($user, $subject) use ($resolveTeamScheduleDraw, $canAccessTeamDraw) {
            if ($subject instanceof \App\Models\Event) {
                return $user->is_event_admin($subject->id) || $user->is_convenor($subject->id);
            }
            $draw = $resolveTeamScheduleDraw($subject);
            return $canAccessTeamDraw($user, $draw);
        });

        Gate::define('team-schedule.manage', function ($user, $subject) use ($resolveTeamScheduleDraw, $canAccessTeamDraw) {
            if ($subject instanceof \App\Models\Event) {
                return $user->is_event_admin($subject->id) || $user->is_convenor($subject->id);
            }
            $draw = $resolveTeamScheduleDraw($subject);
            if (!$draw) {
                return false;
            }
            if ($draw->locked) {
                return false;
            }
            return $canAccessTeamDraw($user, $draw);
        });

        // ── Team Management (TeamController) ──────────────────────────────────
        // Team-scoped abilities: resolve event through team → category → event.
        // Admin or convenor for the event may manage teams within that event.

        Gate::define('team.view', function ($user, \App\Models\Team $team) {
            $event = optional(optional($team->category)->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('team.create', function ($user, \App\Models\CategoryEvent $categoryEvent) {
            $event = optional($categoryEvent->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('team.update', function ($user, \App\Models\Team $team) {
            $event = optional(optional($team->category)->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('team.delete', function ($user, \App\Models\Team $team) {
            $event = optional(optional($team->category)->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('team.players.manage', function ($user, \App\Models\Team $team) {
            $event = optional(optional($team->category)->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('team.admin', function ($user) {
            return $user->hasRole('admin') || $user->hasRole('convenor');
        });

        // ── Individual-fixture abilities (FixtureController) ──────────────────
        // Delegate to DrawPolicy for draw-scoped operations.

        Gate::define('fixture.view', function ($user, \App\Models\Draw $draw) {
            return app(\App\Policies\DrawPolicy::class)->view($user, $draw);
        });

        Gate::define('fixture.update', function ($user, \App\Models\Draw $draw) {
            return app(\App\Policies\DrawPolicy::class)->update($user, $draw);
        });

        // ── Category-event management (EventAdminController) ──────────────────
        // Lock/unlock categories and add/remove players from category events.

        Gate::define('category.manage', function ($user, \App\Models\CategoryEvent $categoryEvent) {
            return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
        });

        // ── Event Finance Authorization (EventFinanceController) ─────────────────────
        // Finance operations are event-scoped and restricted to event admins/convenors.
        // Expenses, income, and convenor fees are only accessible within the authorized event.

        Gate::define('event-finance.view', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-finance.manage', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-finance.expense', function ($user, \App\Models\EventExpense $expense) {
            $event = optional($expense->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-finance.income', function ($user, \App\Models\EventIncomeItem $item) {
            $event = optional($item->event);
            if (!$event) {
                return false;
            }
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-finance.convenor', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        // ── Event Email Authorization (EmailController) ──────────────────────────────
        // Email operations are event-scoped and restricted to event admins/convenors.
        // Sending bulk messages, previews, and delivery logs are authorized per event.

        Gate::define('event-email.view', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-email.send', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-email.bulk-send', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });

        Gate::define('event-email.view-delivery', function ($user, \App\Models\Event $event) {
            return $user->is_event_admin($event->id) || $user->is_convenor($event->id);
        });
    }
}
