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
        // Auto-create the SQLite database file if it doesn't exist yet.
        // This prevents a fatal error on first deploy before migrations run.
        if (config('database.default') === 'sqlite') {
            $path = config('database.connections.sqlite.database');
            if ($path && $path !== ':memory:' && ! file_exists($path)) {
                touch($path);
            }
        }
    }
}
