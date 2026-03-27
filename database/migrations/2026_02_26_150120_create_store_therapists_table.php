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
        Schema::create('store_therapists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_profile_id')->constrained('store_profiles')->onDelete('cascade');
            $table->string('name');
            $table->text('bio')->nullable();
            $table->integer('years_of_experience')->default(0);
            $table->string('profile_photo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_therapists');
    }
};
