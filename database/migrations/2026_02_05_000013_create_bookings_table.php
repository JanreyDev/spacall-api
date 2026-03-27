<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Create sequence for booking numbers if not exists
            DB::statement("CREATE SEQUENCE IF NOT EXISTS booking_number_seq START WITH 1");
        }

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 40)->unique()->nullable();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->foreignId('service_id')->constrained('services')->onDelete('restrict');

            $table->enum('booking_type', ['home_service', 'in_store']);
            $table->enum('schedule_type', ['now', 'scheduled']);

            $table->enum('customer_tier', ['normal', 'vip', 'platinum'])->default('normal');
            $table->enum('assignment_type', ['browsable', 'direct_request'])->default('browsable');

            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();

            $table->enum('status', [
                'awaiting_assignment','pending','accepted','en_route','arrived',
                'in_progress','completed','cancelled','no_show','expired'
            ])->default('awaiting_assignment');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->enum('cancelled_by', ['customer','provider','system'])->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('cancellation_fee', 10, 2)->default(0);

            $table->decimal('service_price', 10, 2)->default(0);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('distance_surcharge', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('promo_discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);

            $table->enum('payment_method', ['wallet','cash','card'])->default('wallet');
            $table->enum('payment_status', ['pending','paid','refunded'])->default('pending');

            $table->text('customer_notes')->nullable();
            $table->text('provider_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('booking_number');
            $table->index('customer_id');
            $table->index('provider_id');
            $table->index('service_id');
            $table->index('status');
            $table->index('scheduled_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Trigger function to generate booking number
            DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION generate_booking_number()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.booking_number IS NULL THEN
                    NEW.booking_number := 'SPC-' || TO_CHAR(CURRENT_DATE, 'YYYY') || '-' || LPAD(nextval('booking_number_seq')::text, 6, '0');
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            SQL
            );

            DB::statement("CREATE TRIGGER bookings_booking_number_trigger BEFORE INSERT ON bookings FOR EACH ROW EXECUTE FUNCTION generate_booking_number();");
        }
    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS bookings_booking_number_trigger ON bookings;");
        DB::statement("DROP FUNCTION IF EXISTS generate_booking_number();");
        DB::statement("DROP SEQUENCE IF EXISTS booking_number_seq;");
        Schema::dropIfExists('bookings');
    }
};
