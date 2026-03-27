<?php

use App\Models\Provider;
use App\Models\ProviderLocation;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "Checking Therapists...\n";

$therapists = Provider::with(['user', 'locations'])->where('type', 'therapist')->get();

echo "Total Therapists: " . $therapists->count() . "\n";

foreach ($therapists as $t) {
    echo "ID: " . $t->id . " | ";
    echo "Name: " . ($t->user ? $t->user->first_name . ' ' . $t->user->last_name : 'No User') . " | ";
    echo "Verified: " . $t->verification_status . " | ";
    echo "Active: " . ($t->is_active ? 'Yes' : 'No') . " | ";
    echo "Available (Online): " . ($t->is_available ? 'Yes' : 'No') . " | ";
    
    $loc = $t->locations()->latest()->first();
    if ($loc) {
        echo "Loc: " . $loc->latitude . ", " . $loc->longitude . " (" . ($loc->is_online ? 'Online' : 'Offline') . ")\n";
    } else {
        echo "Loc: None\n";
    }
}
