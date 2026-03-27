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
        // For Postgres, enum is often a varchar with a check constraint.
        // We drop the existing one and add a new one.
        try {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_gender_check');
        } catch (\Exception $e) {
            // Ignore if it doesn't exist
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IN ('male', 'female', 'lgbt'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_gender_check');
        } catch (\Exception $e) {
            // Ignore
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IN ('male', 'female', 'other', 'prefer_not_to_say'))");
    }
};
