<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('service_categories')->onDelete('set null');
            $table->string('name');
            $table->string('slug')->unique(); // NOT NULL
            $table->text('description')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->text('image_url')->nullable();
            $table->jsonb('benefits')->nullable();
            $table->jsonb('contraindications')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->jsonb('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
