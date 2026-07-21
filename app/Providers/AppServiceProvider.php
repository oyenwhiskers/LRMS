<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isAdmin() ? true : null);

        foreach (config('permissions.catalog', []) as $module => $actions) {
            foreach ($actions as $action) {
                $ability = "{$module}.{$action}";
                Gate::define($ability, fn (User $user): bool => $user->hasPermission($ability));
            }
        }
    }
}
