<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('home_service_enabled')->default(true);
            $table->boolean('store_service_enabled')->default(false);
            $table->unsignedInteger('base_distance_km')->default(5);
            $table->decimal('per_km_surcharge', 10, 2)->default(0);
            $table->unsignedInteger('max_travel_distance_km')->default(10);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['provider_id','service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_services');
    }
};
