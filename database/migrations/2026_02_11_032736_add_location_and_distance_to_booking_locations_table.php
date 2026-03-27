<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Add columns using raw SQL with IF NOT EXISTS to avoid errors
            DB::unprepared("
                DO $$ 
                BEGIN
                    -- Add distance_km if it doesn't exist
                    IF NOT EXISTS (
                        SELECT 1 FROM information_schema.columns 
                        WHERE table_name='booking_locations' AND column_name='distance_km'
                    ) THEN
                        ALTER TABLE booking_locations ADD COLUMN distance_km NUMERIC(8,2);
                    END IF;

                    -- Add location if it doesn't exist
                    IF NOT EXISTS (
                        SELECT 1 FROM information_schema.columns 
                        WHERE table_name='booking_locations' AND column_name='location'
                    ) THEN
                        ALTER TABLE booking_locations ADD COLUMN location GEOGRAPHY(POINT, 4326);
                    END IF;
                END $$;
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE booking_locations DROP COLUMN IF EXISTS distance_km');
            DB::statement('ALTER TABLE booking_locations DROP COLUMN IF EXISTS location');
        }
    }
};
