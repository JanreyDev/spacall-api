<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_documents');
    }
};
