<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

try {
    echo "Creating reviews table..." . PHP_EOL;

    if (!Schema::hasTable('reviews')) {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
        echo "Table reviews created." . PHP_EOL;
    } else {
        echo "Table reviews already exists." . PHP_EOL;
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
