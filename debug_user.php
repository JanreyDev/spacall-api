<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::with('providers')->find(83);
if ($user) {
    echo "Tier: " . $user->customer_tier . "\n";
    echo "Wallet: " . $user->wallet_balance . "\n";
    foreach ($user->providers as $p) {
        echo "Provider [" . $p->type . "] is_available: " . ($p->is_available ? 'TRUE' : 'FALSE') . "\n";
    }
}
