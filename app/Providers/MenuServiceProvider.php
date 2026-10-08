<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
  /**
   * Register services.
   *
   * @return void
   */
  public function register()
  {
    //
  }

  /**
   * Bootstrap services.
   *
   * @return void
   */
  public function boot()
  {
    $verticalMenuJson = file_get_contents(base_path('resources/menu/verticalMenu.json'));
    $verticalMenuData = json_decode($verticalMenuJson);
    $adminMenujson = file_get_contents(base_path('resources/menu/adminMenu.json'));
    $adminMenuData = json_decode($adminMenujson);
    $horizontalMenuJson = file_get_contents(base_path('resources/menu/horizontalMenu.json'));
    $horizontalMenuData = json_decode($horizontalMenuJson);

    // Resolve this after authentication, only for the horizontal navigation.
    \View::composer('layouts.sections.menu.horizontalMenu', function ($view): void {
      $user = auth()->user();
      $view->with('showAdminHome', $user && (
        $user->hasAnyRole(['admin', 'super-user'])
        || $user->can('superUser')
        || $user->adminEvents()->exists()
      ));
    });

    // Share all menuData to all the views
    \View::share('menuData', [$verticalMenuData, $horizontalMenuData, $adminMenuData]);
  }
}
