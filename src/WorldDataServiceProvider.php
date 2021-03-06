<?php

namespace Someshwer\WorldCountries;

use Illuminate\Support\ServiceProvider;
use Someshwer\WorldCountries\Data\DataRepository;
use Someshwer\WorldCountries\Lib\World;

/**
 * Author: Someshwer Bandapally
 * Date: 26-05-2018.
 *
 * Class WorldDataServiceProvider
 */
class WorldDataServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('bs-world', function () {
            return new World(new DataRepository());
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadRoutesFrom(__DIR__ . '/routes/routes.php');
        $this->publishes([__DIR__.'/Config/world.php' => config_path('world.php')], 'config');
    }
}
