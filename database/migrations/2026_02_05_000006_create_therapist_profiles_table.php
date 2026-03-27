<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('therapist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');

            // Professional Details
            $table->jsonb('specializations')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedInteger('years_of_experience')->nullable();
            $table->jsonb('certifications')->nullable();
            $table->jsonb('languages_spoken')->nullable();
            $table->text('professional_photo_url')->nullable();

            // Licensing
            $table->string('license_number')->nullable();
            $table->string('license_type')->nullable();
            $table->date('license_expiry_date')->nullable();

            // Pricing & Service Radius
            $table->decimal('base_rate', 10, 2)->default(0);
            $table->unsignedInteger('service_radius_km')->default(5);

            // Location
            $table->decimal('base_location_latitude', 10, 7)->nullable();
            $table->decimal('base_location_longitude', 10, 7)->nullable();
            $table->string('base_address')->nullable();

            // Availability & Equipment
            $table->jsonb('default_schedule')->nullable();
            $table->boolean('has_own_equipment')->default(true);
            $table->jsonb('equipment_list')->nullable();

            $table->timestamps();

            $table->index('provider_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            $postgisExists = DB::select("SELECT count(*) FROM pg_extension WHERE extname = 'postgis'");
            if (!empty($postgisExists) && $postgisExists[0]->count > 0) {
                DB::statement("ALTER TABLE therapist_profiles ADD COLUMN IF NOT EXISTS base_location GEOGRAPHY(POINT,4326);");

                DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION update_therapist_base_location()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.base_location_latitude IS NOT NULL AND NEW.base_location_longitude IS NOT NULL THEN
        NEW.base_location := ST_SetSRID(ST_MakePoint(NEW.base_location_longitude, NEW.base_location_latitude), 4326);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL
                );

                DB::statement("DROP TRIGGER IF EXISTS set_base_location_trigger ON therapist_profiles;");
                DB::statement("CREATE TRIGGER set_base_location_trigger BEFORE INSERT OR UPDATE ON therapist_profiles FOR EACH ROW EXECUTE FUNCTION update_therapist_base_location();");
                DB::statement("CREATE INDEX IF NOT EXISTS therapist_profiles_base_location_idx ON therapist_profiles USING GIST (base_location);");
            } else {
                \Log::warning('PostGIS extension not available for therapist_profiles. Skipping spatial column.');
            }
        }
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS set_base_location_trigger ON therapist_profiles;");
        DB::statement("DROP FUNCTION IF EXISTS update_therapist_base_location();");
        Schema::dropIfExists('therapist_profiles');
    }
};
