<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Laravel\Fortify\Contracts\LoginResponse;
use App\Http\Responses\LoginResponse as CustomLoginResponse;
use App\Models\CategoryEventRegistration;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register()
  {
    // ✅ Override Fortify login redirect behaviour
    $this->app->singleton(LoginResponse::class, CustomLoginResponse::class);
  }

  /**
   * Bootstrap any application services.
   */
  public function boot()
  {
    // ✅ Global admin badge: pending bank refunds
    View::composer('*', function ($view) {

      if (auth()->check() && auth()->user()->hasRole('super-user')) {

        $pendingBankRefundCount = CategoryEventRegistration::where('status', 'withdrawn')
          ->where('refund_method', 'bank')
          ->where('refund_status', 'pending')
          ->count();

        $view->with('pendingBankRefundCount', $pendingBankRefundCount);
      }

    });
  }
}
