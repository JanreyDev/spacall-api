<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('booking_locations', function (Blueprint $table) {
            // Only add distance_km, skip location (geometry) because PostGIS is missing on live server
            if (!Schema::hasColumn('booking_locations', 'distance_km')) {
                $table->decimal('distance_km', 10, 2)->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_locations', function (Blueprint $table) {
            if (Schema::hasColumn('booking_locations', 'distance_km')) {
                $table->dropColumn(['distance_km']);
            }
        });
    }
};
