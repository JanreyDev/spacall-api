<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->string('address');
            $table->string('barangay')->nullable();
            $table->string('city');
            $table->string('province');
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('landmark')->nullable();
            $table->text('delivery_instructions')->nullable();
            $table->timestamps();
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_locations');
    }
};
