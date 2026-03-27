<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Storage;

echo "FILESYSTEM_DISK: " . env('FILESYSTEM_DISK') . "\n";
echo "Config default: " . config('filesystems.default') . "\n\n";

$users = User::whereNotNull('profile_photo_url')->get();
echo "Found " . $users->count() . " users with profile photos.\n\n";

foreach ($users as $user) {
    echo "User ID: " . $user->id . "\n";
    echo "DB URL: " . ($user->profile_photo_url ?: 'EMPTY') . "\n";
    
    // Try to extract relative path to see what Storage::url would return
    $relativePath = str_replace('/storage/', '', $user->profile_photo_url);
    if (!str_starts_with($relativePath, 'profile_photos/')) {
         // Fallback or cleanup
    }
    
    echo "Storage::url('profile_photos/somefile.jpg'): " . Storage::url('profile_photos/somefile.jpg') . "\n";
    echo "--------------------------\n";
}
