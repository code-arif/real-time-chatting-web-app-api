<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register broadcasting routes with Sanctum auth middleware
        Broadcast::routes([
            'middleware' => ['api', 'auth:sanctum'],
            'prefix' => 'api/v1'
        ]);

        require base_path('routes/channels.php');
    }
}
