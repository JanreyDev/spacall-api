<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

// 1. Delete duplicates by store_name (if name is missing)
$emptyNameGroups = App\Models\StoreProfile::whereNull('store_name')
    ->orWhere('store_name', '')
    ->get()
    ->groupBy('provider_id'); // Group by provider first if name is empty

foreach ($emptyNameGroups as $id => $group) {
    if ($group->count() > 1) {
        $group->sortByDesc('id')->skip(1)->each(function ($store) {
            $store->delete();
            echo "Deleted empty name duplicate store ID: {$store->id}\n";
        });
    }
}

// 2. Delete duplicates by provider_id
$providerGroups = App\Models\StoreProfile::all()->groupBy('provider_id');
foreach ($providerGroups as $id => $group) {
    if ($group->count() > 1) {
        $group->sortByDesc('id')->skip(1)->each(function ($store) {
            $store->delete();
            echo "Deleted duplicate provider store ID: {$store->id}\n";
        });
    }
}

echo "Cleanup complete.\n";
