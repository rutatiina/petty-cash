<?php

namespace Rutatiina\PettyCash;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class PettyCashServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__.'/routes/routes.php';
        //include __DIR__.'/routes/api.php';

        $this->loadViewsFrom(__DIR__.'/resources/views', 'petty-cash');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->make('Rutatiina\PettyCash\Http\Controllers\PettyCashController');
    }
}
