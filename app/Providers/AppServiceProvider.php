<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        $this->configureRateLimiting();
    }

    /**
     * Query membaca seluruh tabel data, jadi dibatasi per pengguna agar satu
     * pengguna tidak membebani SQLite. Data widget lebih longgar karena satu
     * laman dashboard memuat banyak widget sekaligus.
     */
    private function configureRateLimiting(): void
    {
        $tooMany = fn () => response()->json([
            'message' => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
        ], 429);

        RateLimiter::for('queries', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip())
            ->response($tooMany));

        RateLimiter::for('widgets', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip())
            ->response($tooMany));
    }
}
