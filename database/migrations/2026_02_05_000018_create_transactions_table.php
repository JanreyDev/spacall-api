<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id')->unique();
            $table->morphs('transactable');
            $table->string('type');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('PHP');
            $table->string('status')->default('pending');
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS transactions_transactable_index ON transactions (transactable_type, transactable_id);");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
