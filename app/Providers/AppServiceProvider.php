<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\model;
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
        model::shouldBeStrict(! $this->app->isProduction());
    }
}
