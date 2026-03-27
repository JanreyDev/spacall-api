<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$firstName = 'JanreyStore';
$user = App\Models\User::where('first_name', 'like', "%{$firstName}%")->first();

if (!$user || !$user->provider) {
    echo "Provider not found.\n";
    exit;
}

$p = $user->provider;
$p->verification_status = 'verified';
$p->is_available = true;
$p->business_hours = ['monday' => ['09:00', '21:00']]; // Set default hours
$p->save();

echo "Provider {$user->first_name} manually verified and set to available.\n";
