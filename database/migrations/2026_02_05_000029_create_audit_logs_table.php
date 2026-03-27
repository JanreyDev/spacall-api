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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Polymorphic auditable columns (avoid morphs() to control index creation)
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');

            $table->string('event')->comment('created, updated, deleted, etc.');

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();

            $table->timestamp('created_at');

            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Create auditable index only if it doesn't exist (avoid duplicate index errors)
            DB::statement("CREATE INDEX IF NOT EXISTS audit_logs_auditable_type_auditable_id_index ON audit_logs (auditable_type, auditable_id)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
