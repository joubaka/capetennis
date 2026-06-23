<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Order;
use App\Models\RegistrationOrder;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Observers\FinancialMutationObserver;
use App\Observers\RegistrationObserver;
use App\Observers\TransactionObserver;
use App\Observers\WalletObserver;
use App\Observers\WalletTransactionObserver;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register()
  {
    // ✅ Override Fortify login redirect behaviour
    $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);

    // ---------------------------------------------------------------
    // Draw domain — canonical services
    // ---------------------------------------------------------------
    $this->app->singleton(\App\Domain\Draws\Services\StandingsService::class);
    $this->app->singleton(\App\Domain\Draws\Services\RoundRobinGenerationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\PlayoffGenerationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\ByeAdvancementService::class);
    $this->app->singleton(\App\Domain\Draws\Services\DrawLockService::class);
    $this->app->singleton(\App\Domain\Draws\Services\DrawPublicationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\BracketRenderService::class);
    $this->app->singleton(\App\Domain\Draws\Services\FeedInGenerationService::class);
    $this->app->singleton(\App\Domain\Draws\Services\DrawGenerationService::class);
    $this->app->singleton(\App\Domain\Fixtures\Services\FixtureProgressionService::class);

    // EngineRouter — singleton so mismatch/fallback counters accumulate per request
    $this->app->singleton(\App\Domain\Engine\EngineRouter::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot()
  {
    // Share asset version globally for cache busting
    View::share('assetVersion', config('app.asset_version', '1.0.0'));

    CategoryEventRegistration::observe(RegistrationObserver::class);
    Wallet::observe(WalletObserver::class);
    WalletTransaction::observe(WalletTransactionObserver::class);
    Transaction::observe(TransactionObserver::class);
    RegistrationOrder::observe(FinancialMutationObserver::class);
    TeamPaymentOrder::observe(FinancialMutationObserver::class);
    CategoryEventRegistration::observe(FinancialMutationObserver::class);
    ClothingOrder::observe(FinancialMutationObserver::class);
    Order::observe(FinancialMutationObserver::class);
    TeamPlayer::observe(FinancialMutationObserver::class);
    // ✅ Global admin badge: pending bank refunds (registrations + team refunds)
    View::composer('*', function ($view) {

      if (auth()->check() && auth()->user()->hasAnyRole(['super-user', 'admin'])) {

        $registrationPending = CategoryEventRegistration::where('status', 'withdrawn')
          ->where('refund_method', 'bank')
          ->where('refund_status', 'pending')
          ->count();

        $teamPending = TeamPaymentOrder::where('refund_method', 'bank')
          ->where('refund_status', 'pending')
          ->count();

        $pendingBankRefundCount = $registrationPending + $teamPending;

        $view->with('pendingBankRefundCount', $pendingBankRefundCount);
      }

    });
  }
}
