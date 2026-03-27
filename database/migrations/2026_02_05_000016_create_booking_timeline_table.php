<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_timeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->enum('status', [
                'awaiting_assignment','pending','accepted','en_route','arrived',
                'in_progress','completed','cancelled','no_show','expired'
            ]);
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index('booking_id');
            $table->index(['booking_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION log_booking_status_change()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
                    INSERT INTO booking_timeline (booking_id, status, created_at) VALUES (NEW.id, NEW.status, NOW());
                ELSIF (TG_OP = 'INSERT') THEN
                    INSERT INTO booking_timeline (booking_id, status, created_at) VALUES (NEW.id, NEW.status, NOW());
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL
            );

            DB::statement("CREATE TRIGGER bookings_status_timeline_trigger AFTER INSERT OR UPDATE ON bookings FOR EACH ROW EXECUTE FUNCTION log_booking_status_change();");
        }
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS bookings_status_timeline_trigger ON bookings;");
        DB::statement("DROP FUNCTION IF EXISTS log_booking_status_change();");
        Schema::dropIfExists('booking_timeline');
    }
};
