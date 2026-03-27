<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tiers', function (Blueprint $table) {
            $table->renameColumn('online_hours_required', 'online_minutes_required');
        });

        // Convert existing hours to minutes
        DB::table('tiers')->get()->each(function ($tier) {
            DB::table('tiers')
                ->where('id', $tier->id)
                ->update(['online_minutes_required' => $tier->online_minutes_required * 60]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert minutes back to hours before renaming
        DB::table('tiers')->get()->each(function ($tier) {
            DB::table('tiers')
                ->where('id', $tier->id)
                ->update(['online_minutes_required' => $tier->online_minutes_required / 60]);
        });

        Schema::table('tiers', function (Blueprint $table) {
            $table->renameColumn('online_minutes_required', 'online_hours_required');
        });
    }
};
