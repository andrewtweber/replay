<?php

namespace Replay;

use Illuminate\Support\ServiceProvider;

class ReplayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/replay.php', 'replay');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/replay.php' => config_path('replay.php'),
            ], 'replay-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'replay-migrations');
        }
    }
}
