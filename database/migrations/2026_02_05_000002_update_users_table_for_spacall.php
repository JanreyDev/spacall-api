<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->timestamp('phone_verified_at')->nullable()->after('mobile_number');
            $table->string('social_provider')->nullable()->after('phone_verified_at');
            $table->string('social_id')->nullable()->after('social_provider');
            $table->string('customer_tier')->default('normal')->after('social_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'uuid',
                'phone',
                'phone_verified_at',
                'social_provider',
                'social_id',
                'customer_tier',
            ]);
        });
    }
};
