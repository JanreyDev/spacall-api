<?php

use App\Models\Provider;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "Updating Therapist ID 17...\n";

$therapist = Provider::find(17);
if ($therapist) {
    $therapist->verification_status = 'verified';
    $therapist->is_available = true;
    $therapist->save();
    echo "Updated! Verified: " . $therapist->verification_status . ", Available: " . $therapist->is_available . "\n";
} else {
    echo "Therapist 17 not found.\n";
    // Fallback: update ANY therapist to verified/available
    $first = Provider::where('type', 'therapist')->first();
    if ($first) {
         $first->verification_status = 'verified';
         $first->is_available = true;
         $first->save();
         echo "Updated fallback ID " . $first->id . "\n";
    }
}
