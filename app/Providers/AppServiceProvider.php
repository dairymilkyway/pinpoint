<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // The default paginator view is Tailwind, and this app is Bootstrap
        // throughout. The audit log is the first screen that pages.
        Paginator::useBootstrapFive();
    }
}
