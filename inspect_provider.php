<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$firstName = 'JanreyStore';
$user = App\Models\User::where('first_name', 'like', "%{$firstName}%")
    ->with(['provider.locations', 'provider.therapistProfile'])
    ->first();

if (!$user) {
    echo "User not found.\n";
    exit;
}

echo "User found: {$user->first_name} {$user->last_name} (ID: {$user->id})\n";
echo "Customer Tier: {$user->customer_tier}\n";

if (!$user->provider) {
    echo "No provider record found.\n";
    exit;
}

$p = $user->provider;
echo "Provider ID: {$p->id}\n";
echo "Type: {$p->type}\n";
echo "Is Available: " . ($p->is_available ? 'true' : 'false') . "\n";
echo "Verification Status: {$p->verification_status}\n";
echo "Is Active: " . ($p->is_active ? 'true' : 'false') . "\n";

if ($p->locations->isNotEmpty()) {
    foreach ($p->locations as $loc) {
        echo "Location ID: {$loc->id}\n";
        echo "Is Online: " . ($loc->is_online ? 'true' : 'false') . "\n";
        echo "Latitude: {$loc->latitude}\n";
        echo "Longitude: {$loc->longitude}\n";
        echo "--------------------------\n";
    }
} else {
    echo "No location records found.\n";
}
