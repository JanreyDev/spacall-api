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
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->enum('vip_status', ['none', 'pending', 'approved', 'rejected'])->default('none')->after('gallery_images');
            $table->timestamp('vip_applied_at')->nullable()->after('vip_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('therapist_profiles', function (Blueprint $table) {
            $table->dropColumn(['vip_status', 'vip_applied_at']);
        });
    }
};
