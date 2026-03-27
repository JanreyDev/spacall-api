<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('type', ['therapist', 'store']);

            $table->enum('verification_status', ['pending','under_review','verified','rejected','suspended'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->string('business_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->jsonb('business_hours')->nullable();

            $table->decimal('commission_rate', 5, 2)->default(15.00);
            $table->decimal('total_earnings', 12, 2)->default(0.00);

            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('total_bookings')->default(0);
            $table->unsignedInteger('completed_bookings')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_accepting_bookings')->default(false);
            $table->boolean('accepts_home_service')->default(true);
            $table->boolean('accepts_store_service')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('type');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
