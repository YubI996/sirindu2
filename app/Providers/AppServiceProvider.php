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

        // Force HTTPS URL generation unconditionally in production
        if (config('app.env') === 'production') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
            $this->app['url']->forceScheme('https');

            // Force request to be treated as HTTPS
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}
