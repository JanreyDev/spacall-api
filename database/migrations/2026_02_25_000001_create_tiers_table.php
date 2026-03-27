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
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('level')->unique();
            $table->integer('online_hours_required')->default(0);
            $table->integer('extensions_required')->default(0);
            $table->integer('bookings_required')->default(0);
            $table->timestamps();
        });

        // Seed initial tiers
        DB::table('tiers')->insert([
            [
                'name' => 'Tier 1',
                'level' => 1,
                'online_hours_required' => 100,
                'extensions_required' => 50,
                'bookings_required' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tier 2',
                'level' => 2,
                'online_hours_required' => 500,
                'extensions_required' => 150,
                'bookings_required' => 250,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tier 3',
                'level' => 3,
                'online_hours_required' => 1000,
                'extensions_required' => 300,
                'bookings_required' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiers');
    }
};
