<?php

namespace App\Providers;

use App\Models\Anak;
use App\Models\DataAnak;
use App\Observers\AnakObserver;
use App\Observers\DataAnakObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     */
    public const HOME = '/admin/home';

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
        // Namespace view "admin" (mis. admin::layouts.app) berada di
        // resources/views/vendor/admin. Path lama __DIR__.'/../views' (app/views)
        // tidak pernah ada → membuat `php artisan view:cache` gagal karena
        // memindai direktori yang tak ada.
        $this->loadViewsFrom(resource_path('views/vendor/admin'), 'admin');

        // Force HTTPS URL generation in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
            $this->app['url']->forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }

        // Rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        DataAnak::observe(DataAnakObserver::class);
        Anak::observe(AnakObserver::class);
    }
}
