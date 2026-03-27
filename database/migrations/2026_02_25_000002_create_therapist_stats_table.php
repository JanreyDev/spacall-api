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
        Schema::create('therapist_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
            $table->unsignedBigInteger('total_online_minutes')->default(0);
            $table->unsignedInteger('total_extensions')->default(0);
            $table->unsignedInteger('total_bookings')->default(0);
            $table->timestamp('last_online_at')->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_stats');
    }
};
