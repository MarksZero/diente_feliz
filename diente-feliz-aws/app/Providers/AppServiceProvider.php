<?php

namespace App\Providers;

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
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Record::class, \App\Policies\RecordPolicy::class);
        foreach (\App\Services\Modules::permissions() as $permission) {
            \Illuminate\Support\Facades\Gate::define($permission, fn(\App\Models\User $user) => $user->enabled() && $user->permits($permission));
        }
        \Illuminate\Support\Facades\RateLimiter::for('exports', fn(\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($r->user()->id));
        \Illuminate\Support\Facades\RateLimiter::for('writes', fn(\Illuminate\Http\Request $r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($r->user()->id));
        \Illuminate\Pagination\Paginator::defaultView('pagination');
    }
}
