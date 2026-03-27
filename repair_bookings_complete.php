<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Repairing bookings table (Comprehensive)..." . PHP_EOL;

    $columnsToAdd = [
        'service_price' => 'DECIMAL(10, 2) DEFAULT 0',
        'distance_km' => 'DECIMAL(8, 2) NULL',
        'distance_surcharge' => 'DECIMAL(10, 2) DEFAULT 0',
        'subtotal' => 'DECIMAL(10, 2) DEFAULT 0',
        'platform_fee' => 'DECIMAL(10, 2) DEFAULT 0',
        'promo_discount' => 'DECIMAL(10, 2) DEFAULT 0',
        'total_amount' => 'DECIMAL(10, 2) DEFAULT 0',
        'payment_method' => "VARCHAR(20) DEFAULT 'wallet'", // Using VARCHAR for ENUM comaptibility
        'payment_status' => "VARCHAR(20) DEFAULT 'pending'",
        'customer_notes' => 'TEXT NULL',
        'provider_notes' => 'TEXT NULL',
        'booking_type' => "VARCHAR(20) NULL",
        'schedule_type' => "VARCHAR(20) NULL",
        'scheduled_at' => 'TIMESTAMP NULL',
        'duration_minutes' => 'INTEGER NULL',
        'cancelled_by' => "VARCHAR(20) NULL",
        'cancellation_reason' => 'TEXT NULL',
        'cancellation_fee' => 'DECIMAL(10, 2) DEFAULT 0',
        'accepted_at' => 'TIMESTAMP NULL',
        'started_at' => 'TIMESTAMP NULL',
        'completed_at' => 'TIMESTAMP NULL',
        'cancelled_at' => 'TIMESTAMP NULL',
    ];

    foreach ($columnsToAdd as $column => $definition) {
        if (!Schema::hasColumn('bookings', $column)) {
            try {
                DB::statement("ALTER TABLE bookings ADD COLUMN $column $definition");
                echo "Added column: $column" . PHP_EOL;
            } catch (\Exception $e) {
                echo "Failed to add $column: " . $e->getMessage() . PHP_EOL;
            }
        } else {
            // echo "Column $column already exists." . PHP_EOL;
        }
    }

    echo "REPAIR COMPLETE" . PHP_EOL;

} catch (\Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . PHP_EOL;
}
