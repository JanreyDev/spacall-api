<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('booking_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating')->unsigned();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        // Placeholder for rating update trigger if needed by application
        // If you need to create a DB trigger, add the SQL here. Omit raw empty statements.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS reviews_update_provider_rating_trigger ON reviews');
        }
        Schema::dropIfExists('reviews');
    }
};
