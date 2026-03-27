<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Provider;
use App\Models\StoreProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Aurora Spa
        $this->createStore(
            'Aurora Spa',
            'Gold-accented suites with private steam rooms. Experience the pinnacle of luxury and relaxation.',
            'contact@auroraspa.ph',
            '09171112222',
            'aurora.jpg',
            'McArthur Highway, San Nicolas',
            'San Nicolas',
            'Tarlac City',
            15.4850,
            120.5890,
            ['Private Steam Room', 'Gold-infused Oils', 'Couple Suites', 'Valet Parking'],
            'Free aromatherapy upgrade',
            4.9
        );

        // 2. Serene Oasis
        $this->createStore(
            'Serene Oasis',
            'Signature Hilot massage with crystal sound baths. A sanctuary for your mind and body.',
            'info@sereneoasis.ph',
            '09183334444',
            'serene.jpg',
            'F. Tañedo St, Poblacion',
            'Poblacion',
            'Tarlac City',
            15.4910,
            120.5950,
            ['Crystal Sound Bath', 'Hilot Massage', 'Organic Tea Bar', 'Meditation Garden'],
            '20% off next visit',
            4.8
        );

        // 3. Golden Lotus Wellness
        $this->createStore(
            'Golden Lotus Wellness',
            'Traditional healing meets modern luxury. Specialized in deep tissue and reflexology.',
            'booking@goldenlotus.ph',
            '09195556666',
            'lotus.jpg',
            'Matatalaib',
            'Matatalaib',
            'Tarlac City',
            15.4780,
            120.5820,
            ['Reflexology Walk', 'Herbal Soaks', 'VIP Lounges', 'Sauna'],
            'Free herbal tea',
            4.7
        );
    }

    private function createStore(
        string $storeName,
        string $description,
        string $email,
        string $mobileNumber,
        string $photoUrl,
        string $address,
        string $barangay,
        string $city,
        float $latitude,
        float $longitude,
        array $amenities,
        string $promo,
        float $rating
    ) {
        // Check if user exists
        $user = User::where('mobile_number', $mobileNumber)->first();

        if (!$user) {
            $user = User::create([
                'first_name' => $storeName,
                'last_name' => '(Store)',
                'mobile_number' => $mobileNumber,
                'email' => $email, // Add email if User model supports it, otherwise skip or use username
                'password' => Hash::make('password'),
                'role' => 'provider',
                'status' => 'active',
                'is_verified' => true,
                'profile_photo_url' => "https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=500&auto=format&fit=crop&q=60", // Placeholder
                'customer_tier' => 'store', // Stores use the store tier
            ]);
        }

        // Check if provider profile exists
        $provider = Provider::where('user_id', $user->id)->first();

        if (!$provider) {
            $provider = Provider::create([
                'user_id' => $user->id,
                'type' => 'store',
                'business_name' => $storeName,
                'is_active' => true,
                'is_available' => true,
                'is_accepting_bookings' => true,
                'accepts_store_service' => true,
                'accepts_home_service' => false,
                'average_rating' => $rating,
                'commission_rate' => 20.00,
                'verification_status' => 'approved',
                'verified_at' => now(),
            ]);
        }

        // Create Store Profile
        StoreProfile::updateOrCreate(
            ['provider_id' => $provider->id],
            [
                'store_name' => $storeName,
                'description' => $description,
                'address' => $address,
                'barangay' => $barangay,
                'city' => $city,
                'province' => 'Tarlac',
                'postal_code' => '2300',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'amenities' => json_encode($amenities),
                'photos' => json_encode([$photoUrl]),
            ]
        );
        
        $this->command->info("Seeded Store: {$storeName}");
    }
}
