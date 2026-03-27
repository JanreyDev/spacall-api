<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->decimal('balance', 14, 2)->default(0);
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            // Trigger to auto-create wallet on user insert
            DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION create_wallet_for_user()
            RETURNS TRIGGER AS $$
            BEGIN
                INSERT INTO wallets (user_id, balance, created_at, updated_at)
                VALUES (NEW.id, 0, NOW(), NOW())
                ON CONFLICT (user_id) DO NOTHING;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL
            );

            DB::statement("CREATE TRIGGER users_wallet_trigger AFTER INSERT ON users FOR EACH ROW EXECUTE FUNCTION create_wallet_for_user();");
        }
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS users_wallet_trigger ON users;");
        DB::statement("DROP FUNCTION IF EXISTS create_wallet_for_user();");
        Schema::dropIfExists('wallets');
    }
};
