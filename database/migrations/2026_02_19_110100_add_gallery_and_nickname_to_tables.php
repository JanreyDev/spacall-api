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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('last_name');
        });

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->json('gallery_images')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nickname');
        });

        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->dropColumn('gallery_images');
        });
    }
};
