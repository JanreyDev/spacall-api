<?php
use App\Models\StoreProfile;
use App\Models\Provider;
use App\Models\User;

try {
    $user = User::where('customer_tier', 'store')->first();
    if (!$user) {
        echo "No store user found\n";
        exit;
    }
    echo "Testing for user: " . $user->id . "\n";
    $provider = $user->providers()->first();
    if ($provider) {
        echo "Provider ID: " . $provider->id . "\n";
        $profile = StoreProfile::where('provider_id', $provider->id)->first();
        if ($profile) {
            echo "Current Profile Name: " . $profile->store_name . "\n";
        } else {
            echo "No profile found for this provider\n";
        }
    } else {
        echo "No provider found for this user\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
