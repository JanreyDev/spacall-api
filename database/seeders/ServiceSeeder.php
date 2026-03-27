<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Deactivate existing categories to ensure "only 2"
        \App\Models\ServiceCategory::query()->update(['is_active' => false]);

        // 1. Create/Update the 2 Main Categories
        $massage = \App\Models\ServiceCategory::updateOrCreate(
            ['slug' => 'massage-services'],
            [
                'name' => 'Massage Services',
                'description' => 'Professional massage therapies for rejuvenation.',
                'is_active' => true,
            ]
        );

        $pedicureManicure = \App\Models\ServiceCategory::updateOrCreate(
            ['slug' => 'pedicure-manicure'],
            [
                'name' => 'Pedicure & Manicure',
                'description' => 'Elite foot and hand care treatments.',
                'is_active' => true,
            ]
        );

        // 2. Create Services
        $services = [
            // Massage Services
            ['category_id' => $massage->id, 'name' => 'Quick Relief Massage', 'slug' => 'quick-relief', 'short_description' => 'Instant Stress Relief', 'base_price' => 150.00, 'duration_minutes' => 3, 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ae6ce6db874?w=500'],
            ['category_id' => $massage->id, 'name' => 'Hilot', 'slug' => 'hilot', 'short_description' => 'Traditional Filipino Massage', 'base_price' => 800.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ae6ce6db874?w=500'],
            ['category_id' => $massage->id, 'name' => 'Ventosa Massage', 'slug' => 'ventosa-massage', 'short_description' => 'Cupping Therapy', 'base_price' => 950.00, 'duration_minutes' => 90, 'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=500'],
            ['category_id' => $massage->id, 'name' => 'Dagdagay', 'slug' => 'dagdagay', 'short_description' => 'Traditional Foot Massage', 'base_price' => 700.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=500'],
            ['category_id' => $massage->id, 'name' => 'Swedish Massage', 'slug' => 'swedish-massage', 'short_description' => 'Relaxing Strokes', 'base_price' => 750.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1600334129128-685c5582fd35?w=500'],
            ['category_id' => $massage->id, 'name' => 'Deep Tissue Massage', 'slug' => 'deep-tissue-massage', 'short_description' => 'Strong Pressure', 'base_price' => 1100.00, 'duration_minutes' => 90, 'image_url' => 'https://images.unsplash.com/photo-1519415562275-3b6cf749c00a?w=500'],
            ['category_id' => $massage->id, 'name' => 'Aromatherapy Massage', 'slug' => 'aromatherapy-massage', 'short_description' => 'Essential Oils', 'base_price' => 900.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1616394584738-fc6e612e71b9?w=500'],
            ['category_id' => $massage->id, 'name' => 'Hot Stone Massage', 'slug' => 'hot-stone-massage', 'short_description' => 'Themal Healing', 'base_price' => 1400.00, 'duration_minutes' => 90, 'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecee?w=500'],
            ['category_id' => $massage->id, 'name' => 'Shiatsu', 'slug' => 'shiatsu', 'short_description' => 'Finger Pressure', 'base_price' => 850.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=500'],
            ['category_id' => $massage->id, 'name' => 'Thai Massage', 'slug' => 'thai-massage', 'short_description' => 'Guided Stretching', 'base_price' => 900.00, 'duration_minutes' => 120, 'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=500'],
            ['category_id' => $massage->id, 'name' => 'Foot Reflexology', 'slug' => 'foot-reflexology', 'short_description' => 'Pressure Points', 'base_price' => 650.00, 'duration_minutes' => 45, 'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=500'],

            // Pedicure & Manicure Services
            ['category_id' => $pedicureManicure->id, 'name' => 'Regular Pedicure', 'slug' => 'regular-pedicure', 'short_description' => 'Basic Care', 'base_price' => 350.00, 'duration_minutes' => 30, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Spa Pedicure', 'slug' => 'spa-pedicure', 'short_description' => 'Deep Cleaning', 'base_price' => 500.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1519415562275-3b6cf749c00a?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Gel Pedicure', 'slug' => 'gel-pedicure', 'short_description' => 'Long Lasting', 'base_price' => 700.00, 'duration_minutes' => 45, 'image_url' => 'https://images.unsplash.com/photo-1522337360788-8b13df772ce5?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'French Pedicure', 'slug' => 'french-pedicure', 'short_description' => 'Classic Elegance', 'base_price' => 450.00, 'duration_minutes' => 45, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Athletic Pedicure', 'slug' => 'athletic-pedicure', 'short_description' => 'For Active Feet', 'base_price' => 600.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Paraffin Pedicure', 'slug' => 'paraffin-pedicure', 'short_description' => 'Soft & Smooth', 'base_price' => 800.00, 'duration_minutes' => 75, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Hot Stone Pedicure', 'slug' => 'hot-stone-pedicure', 'short_description' => 'Relaxing Warmth', 'base_price' => 900.00, 'duration_minutes' => 90, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Waterless Pedicure', 'slug' => 'waterless-pedicure', 'short_description' => 'Eco-Friendly', 'base_price' => 500.00, 'duration_minutes' => 45, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Chocolate Pedicure', 'slug' => 'chocolate-pedicure', 'short_description' => 'Sweet Treatment', 'base_price' => 850.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Margarita Pedicure', 'slug' => 'margarita-pedicure', 'short_description' => 'Refreshingly Cool', 'base_price' => 850.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Fish Pedicure', 'slug' => 'fish-pedicure', 'short_description' => 'Exfoliating Nibbles', 'base_price' => 1200.00, 'duration_minutes' => 30, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Milk and Honey Pedicure', 'slug' => 'milk-honey-pedicure', 'short_description' => 'Nourishing Soak', 'base_price' => 950.00, 'duration_minutes' => 75, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Wine Pedicure', 'slug' => 'wine-pedicure', 'short_description' => 'Antioxidant Rich', 'base_price' => 1100.00, 'duration_minutes' => 60, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
            ['category_id' => $pedicureManicure->id, 'name' => 'Shanghai Pedicure', 'slug' => 'shanghai-pedicure', 'short_description' => 'Blade Exfoliation', 'base_price' => 1000.00, 'duration_minutes' => 45, 'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490998670d?w=500'],
        ];

        foreach ($services as $service) {
            $base = [
                'category_id' => $service['category_id'],
                'name' => $service['name'],
                'short_description' => $service['short_description'],
                'base_price' => $service['base_price'],
                'vip_price' => $service['base_price'],
                'duration_minutes' => $service['duration_minutes'],
                'image_url' => $service['image_url'],
                'currency' => 'PHP',
                'description' => $service['short_description'] . ' luxury experience.',
                'is_active' => true,
            ];
            \App\Models\Service::updateOrCreate(['slug' => $service['slug']], $base);
        }
    }
}
