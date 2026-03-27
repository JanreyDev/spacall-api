<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

$duplicates = App\Models\StoreProfile::all()
    ->groupBy('business_name')
    ->filter(function ($group) {
        return $group->count() > 1;
    })
    ->map(function ($group) {
        return $group->count();
    });

echo "Duplicate Business Names found:\n";
print_r($duplicates->toArray());

$duplicatesById = App\Models\StoreProfile::all()
    ->groupBy('provider_id')
    ->filter(function ($group) {
        return $group->count() > 1;
    })
    ->map(function ($group) {
        return $group->count();
    });
    
echo "\nDuplicate Provider IDs found:\n";
print_r($duplicatesById->toArray());
