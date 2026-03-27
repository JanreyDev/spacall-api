<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');

            $table->string('store_name');
            $table->text('description')->nullable();

            $table->string('address');
            $table->string('barangay')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->jsonb('amenities')->nullable();
            $table->jsonb('photos')->nullable();

            $table->timestamps();

            $table->index('provider_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            $postgisExists = DB::select("SELECT count(*) FROM pg_extension WHERE extname = 'postgis'");
            if (!empty($postgisExists) && $postgisExists[0]->count > 0) {
                DB::statement("ALTER TABLE store_profiles ADD COLUMN IF NOT EXISTS location GEOGRAPHY(POINT,4326);");

                DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION update_store_location()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.latitude IS NOT NULL AND NEW.longitude IS NOT NULL THEN
        NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL
                );

                DB::statement("CREATE TRIGGER store_profiles_location_trigger BEFORE INSERT OR UPDATE ON store_profiles FOR EACH ROW EXECUTE FUNCTION update_store_location();");
                DB::statement("CREATE INDEX IF NOT EXISTS store_profiles_location_idx ON store_profiles USING GIST (location);");
            } else {
                \Log::warning('PostGIS extension not available for store_profiles. Skipping spatial column.');
            }
        }
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS store_profiles_location_trigger ON store_profiles;");
        DB::statement("DROP FUNCTION IF EXISTS update_store_location();");
        Schema::dropIfExists('store_profiles');
    }
};
