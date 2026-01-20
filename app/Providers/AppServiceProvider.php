<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../views','admin');

        // Force HTTPS URL generation when behind proxy/load balancer
        if (config('app.env') === 'production' || isset($_SERVER['HTTPS'])) {
            \Illuminate\Support\Facades\URL::forceScheme('https');

            // Ensure asset URLs also use HTTPS
            $this->app['url']->forceScheme('https');
        }
    }
}
