<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Provider;
use App\Models\TherapistProfile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class TherapistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $therapists = [
            // Classic Therapists
            ['first' => 'John', 'middle' => 'Quincy', 'last' => 'Doe', 'spec' => ['Swedish', 'Deep Tissue'], 'available' => true, 'tier' => 'classic'],
            ['first' => 'Maria', 'middle' => 'Santos', 'last' => 'Garcia', 'spec' => ['Prenatal', 'Aromatherapy'], 'available' => true, 'tier' => 'classic'],
            ['first' => 'Robert', 'middle' => 'Lee', 'last' => 'Smith', 'spec' => ['Sports Massage', 'Reflexology'], 'available' => true, 'tier' => 'classic'],
            ['first' => 'Elena', 'middle' => 'Cruz', 'last' => 'Perez', 'spec' => ['Facial', 'Hot Stone'], 'available' => true, 'tier' => 'classic'],
            ['first' => 'David', 'middle' => 'Tan', 'last' => 'Ang', 'spec' => ['Thai Massage', 'Shiatsu'], 'available' => true, 'tier' => 'classic'],
            ['first' => 'Sarah', 'middle' => 'Jane', 'last' => 'Miller', 'spec' => ['Clinical Massage'], 'available' => false, 'tier' => 'classic'],
            ['first' => 'Michael', 'middle' => 'A.', 'last' => 'Wilson', 'spec' => ['Physiotherapy'], 'available' => false, 'tier' => 'classic'],
            ['first' => 'Lisa', 'middle' => 'M.', 'last' => 'Brown', 'spec' => ['Reiki'], 'available' => false, 'tier' => 'classic'],
            
            // VIP Therapists (Elite/Top Therapists)
            ['first' => 'Isabella', 'middle' => 'V.', 'last' => 'Lux', 'spec' => ['Premium Hilot', 'Gold Facial'], 'available' => true, 'tier' => 'vip'],
            ['first' => 'Alexander', 'middle' => 'R.', 'last' => 'King', 'spec' => ['Elite Deep Tissue', 'Hot Stone'], 'available' => true, 'tier' => 'vip'],
            ['first' => 'Sophia', 'middle' => 'M.', 'last' => 'Grace', 'spec' => ['Aromatherapy Luxury', 'Shiatsu'], 'available' => true, 'tier' => 'vip'],
            ['first' => 'Marcus', 'middle' => 'D.', 'last' => 'Imperial', 'spec' => ['Sports Recovery', 'Hilot'], 'available' => true, 'tier' => 'vip'],

            // Store-Based Therapists
            ['first' => 'Angela', 'middle' => 'S.', 'last' => 'Store', 'spec' => ['Spa Pedicure', 'Foot Spa'], 'available' => true, 'tier' => 'store'],
        ];

        foreach ($therapists as $index => $t) {
            // Create/Update User
            $user = User::updateOrCreate(
                ['mobile_number' => '09' . str_pad($index + 10, 9, '0', STR_PAD_LEFT)],
                [
                    'uuid' => (string) Str::uuid(),
                    'first_name' => $t['first'],
                    'last_name' => $t['last'],
                    'gender' => ($index % 2 == 0) ? 'male' : 'female',
                    'date_of_birth' => '1990-01-01',
                    'pin_hash' => Hash::make('123456'),
                    'is_verified' => true,
                    'role' => 'therapist',
                    'customer_tier' => $t['tier'] ?? 'classic',
                ]
            );

            // Create/Update Provider
            $provider = Provider::updateOrCreate(
                ['user_id' => $user->id, 'type' => 'therapist'],
                [
                    'uuid' => (string) Str::uuid(),
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'commission_rate' => 15.00,
                    'average_rating' => 4.5 + (rand(0, 5) / 10),
                    'total_reviews' => rand(10, 100),
                    'is_active' => true,
                    'is_available' => $t['available'],
                    'is_accepting_bookings' => true,
                    'accepts_home_service' => true,
                    'accepts_store_service' => false,
                ]
            );

            // Create/Update Therapist Profile
            TherapistProfile::updateOrCreate(
                ['provider_id' => $provider->id],
                [
                    'specializations' => $t['spec'],
                    'bio' => "Professional therapist specialized in " . implode(', ', $t['spec']) . ".",
                    'years_of_experience' => rand(2, 15),
                    'certifications' => ['Certified Massage Therapist', 'Health & Safety Certified'],
                    'languages_spoken' => ['English', 'Filipino'],
                    'license_number' => 'TRP-' . rand(1000, 9999),
                    'license_type' => 'Professional',
                    'base_rate' => rand(500, 1500),
                    'service_radius_km' => 10,
                    'base_location_latitude' => 14.5 + (rand(0, 100) / 1000),
                    'base_location_longitude' => 120.9 + (rand(0, 100) / 1000),
                    'base_address' => 'Sample Address ' . ($index + 1),
                    'default_schedule' => [
                        'monday' => ['09:00', '18:00'],
                        'tuesday' => ['09:00', '18:00'],
                        'wednesday' => ['09:00', '18:00'],
                        'thursday' => ['09:00', '18:00'],
                        'friday' => ['09:00', '18:00'],
                    ],
                ]
            );

            // Assign services to therapist
            $services = \App\Models\Service::inRandomOrder()->take(rand(2, 4))->get();
            foreach ($services as $service) {
                $provider->services()->attach($service->id, [
                    'price' => $service->base_price + rand(-100, 200),
                    'is_available' => true
                ]);
            }
        }
    }
}
