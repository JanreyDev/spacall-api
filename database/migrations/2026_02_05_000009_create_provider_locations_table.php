<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            $postgisExists = DB::select("SELECT count(*) FROM pg_extension WHERE extname = 'postgis'");
            if (!empty($postgisExists) && $postgisExists[0]->count > 0) {
                DB::statement("CREATE EXTENSION IF NOT EXISTS postgis;");
                DB::statement("ALTER TABLE provider_locations ADD COLUMN IF NOT EXISTS location geography(Point,4326);");
                DB::statement("CREATE INDEX IF NOT EXISTS provider_locations_location_gist_idx ON provider_locations USING GIST (location);");
            } else {
                \Log::warning('PostGIS extension not available for provider_locations. Skipping spatial column.');
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS provider_locations_location_gist_idx');
        Schema::dropIfExists('provider_locations');
    }
};
