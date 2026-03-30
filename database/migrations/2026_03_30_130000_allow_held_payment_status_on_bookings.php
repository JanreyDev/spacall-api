<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_payment_status_check");
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('pending', 'held', 'paid', 'refunded'))");
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM('pending','held','paid','refunded') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("UPDATE bookings SET payment_status = 'pending' WHERE payment_status = 'held'");
            DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_payment_status_check");
            DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_payment_status_check CHECK (payment_status IN ('pending', 'paid', 'refunded'))");
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("UPDATE bookings SET payment_status = 'pending' WHERE payment_status = 'held'");
            DB::statement("ALTER TABLE bookings MODIFY payment_status ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending'");
        }
    }
};
