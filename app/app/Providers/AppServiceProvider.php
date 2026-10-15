<?php

namespace App\Providers;

use App\Services\SettingService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    public function boot(): void
    {
        \Livewire\Livewire::addPersistentMiddleware([
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}