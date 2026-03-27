<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
            $table->enum('assignment_type', ['direct_request','browsed'])->default('browsed');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->enum('response', ['accepted','declined','expired'])->nullable();
            $table->text('decline_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_assignments');
    }
};
