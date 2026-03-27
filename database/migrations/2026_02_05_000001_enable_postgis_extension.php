<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (DB::getDriverName() === 'pgsql') {
            $postgisExists = DB::select("SELECT count(*) FROM pg_available_extensions WHERE name = 'postgis'");
            if (!empty($postgisExists) && $postgisExists[0]->count > 0) {
                // Enable Post GIS extensions if they are available
                DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
                DB::statement('CREATE EXTENSION IF NOT EXISTS postgis_topology;');
            } else {
                \Log::warning('PostGIS extension not available in PostgreSQL. Spatial features will be disabled.');
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Note: Dropping extensions in down() may affect other objects depending on PostGIS.
        DB::statement('DROP EXTENSION IF EXISTS postgis_topology;');
        DB::statement('DROP EXTENSION IF EXISTS postgis;');
    }
};
