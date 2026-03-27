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
        // Use 'api' + 'auth:sanctum' so mobile Bearer tokens are recognized.
        // The default 'web' middleware uses session cookies which mobile apps don't have.
        Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
