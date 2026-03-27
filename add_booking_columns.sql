-- Manual SQL to add missing columns to booking_locations table
-- Run this directly in your PostgreSQL database to bypass the stuck transaction

-- First, make sure we're not in a transaction
COMMIT;

-- Add distance_km column if it doesn't exist
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='booking_locations' AND column_name='distance_km'
    ) THEN
        ALTER TABLE booking_locations ADD COLUMN distance_km NUMERIC(8,2);
        RAISE NOTICE 'Added distance_km column';
    ELSE
        RAISE NOTICE 'distance_km column already exists';
    END IF;
END $$;

-- Add location geography column if it doesn't exist
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='booking_locations' AND column_name='location'
    ) THEN
        ALTER TABLE booking_locations ADD COLUMN location GEOGRAPHY(POINT, 4326);
        RAISE NOTICE 'Added location column';
    ELSE
        RAISE NOTICE 'location column already exists';
    END IF;
END $$;

-- Verify the columns were added
SELECT column_name, data_type, udt_name
FROM information_schema.columns
WHERE table_name = 'booking_locations'
AND column_name IN ('distance_km', 'location');
