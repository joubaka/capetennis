<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\CategoryEventRegistration;
use App\Models\Wallet;
use App\Policies\RegistrationPolicy;
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
        
        //

       // Implicitly grant "Super-Admin" role all permission checks using can()
       Gate::before(function ($user, $ability) {
           if ($user->hasRole('super-user')) {
               return true;
           }
       });
    }
}
