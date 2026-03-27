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
        Schema::table('providers', function (Blueprint $table) {
            if (!Schema::hasColumn('providers', 'accepts_home_service')) {
                $table->boolean('accepts_home_service')->default(true)->after('is_accepting_bookings');
            }
            if (!Schema::hasColumn('providers', 'accepts_store_service')) {
                $table->boolean('accepts_store_service')->default(false)->after('accepts_home_service');
            }
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['accepts_home_service', 'accepts_store_service']);
        });
    }
};
