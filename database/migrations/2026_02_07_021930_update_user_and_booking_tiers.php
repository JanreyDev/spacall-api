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
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('customer_tier')->default('classic')->change();
            });
        }

        // Update existing 'normal' to 'classic'
        DB::table('users')->where('customer_tier', 'normal')->update(['customer_tier' => 'classic']);

        // Update bookings table
        // We can use Schema here too if we want to be safe, but since it's an enum, 
        // changing it to classic while it was normal might be tricky if classic isn't in the enum.
        // Wait, the original migration had: $table->enum('customer_tier', ['normal', 'vip', 'platinum'])->default('normal');
        // 'classic' is NOT in that list. We MUST update the enum values first.
        
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_customer_tier_check");
            DB::statement("ALTER TABLE bookings ALTER COLUMN customer_tier TYPE VARCHAR(255)");
            DB::statement("UPDATE bookings SET customer_tier = 'classic' WHERE customer_tier = 'normal'");
            DB::statement("ALTER TABLE bookings ALTER COLUMN customer_tier SET DEFAULT 'classic'");
        } else {
            // For SQLite, columns already exist as strings/enums which are compatible
            DB::table('bookings')->where('customer_tier', 'normal')->update(['customer_tier' => 'classic']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('customer_tier')->default('normal')->change();
            });
            DB::table('users')->where('customer_tier', 'classic')->update(['customer_tier' => 'normal']);
            DB::statement("UPDATE bookings SET customer_tier = 'normal' WHERE customer_tier = 'classic'");
            DB::statement("ALTER TABLE bookings ALTER COLUMN customer_tier SET DEFAULT 'normal'");
        } else {
            DB::table('users')->where('customer_tier', 'classic')->update(['customer_tier' => 'normal']);
            DB::table('bookings')->where('customer_tier', 'classic')->update(['customer_tier' => 'normal']);
        }
    }
};
