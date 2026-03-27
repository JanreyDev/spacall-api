<?php

namespace App\Console\Commands;

use App\Models\Provider;
use App\Models\Review;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Console\Command;

class RecalculateRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:recalculate-ratings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate average_rating and total_reviews for all therapists';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $providers = Provider::where('type', 'therapist')->get();
        $this->info("Found " . $providers->count() . " therapists.");

        foreach ($providers as $provider) {
            // Recalculate Ratings
            $reviewStats = Review::where('provider_id', $provider->id)
                ->selectRaw('COUNT(*) as total_count, AVG(rating) as avg_rating')
                ->first();

            $totalReviews = $reviewStats->total_count ?? 0;
            $avgRating = round($reviewStats->avg_rating ?? 0, 2);

            // Recalculate Bookings & Earnings
            $bookingStats = Booking::where('provider_id', $provider->id)
                ->selectRaw('
                    COUNT(CASE WHEN status != \'cancelled\' THEN 1 END) as total_bookings,
                    COUNT(CASE WHEN status = \'completed\' THEN 1 END) as completed_bookings,
                    SUM(CASE WHEN status = \'completed\' THEN total_amount ELSE 0 END) as total_earnings
                ')
                ->first();

            $totalBookings = $bookingStats->total_bookings ?? 0;
            $completedBookings = $bookingStats->completed_bookings ?? 0;
            $totalEarnings = $bookingStats->total_earnings ?? 0;

            $provider->update([
                'total_reviews' => $totalReviews,
                'average_rating' => $avgRating,
                'total_bookings' => $totalBookings,
                'completed_bookings' => $completedBookings,
                'total_earnings' => $totalEarnings,
            ]);

            $this->line("Updated Therapist ID: {$provider->id} | Rev: {$totalReviews} | Rating: {$avgRating} | Bookings: {$totalBookings} | Earnings: {$totalEarnings}");
        }

        $this->info('Recalculation complete!');

        // Recalculate Client Stats
        $clients = User::where('role', 'client')->get();
        $this->info("Found " . $clients->count() . " clients.");

        foreach ($clients as $client) {
            $bookingStats = Booking::where('customer_id', $client->id)
                ->selectRaw('
                    COUNT(CASE WHEN status != \'cancelled\' THEN 1 END) as total_bookings,
                    SUM(CASE WHEN status = \'completed\' THEN total_amount ELSE 0 END) as total_spent
                ')
                ->first();

            $client->update([
                'total_bookings' => $bookingStats->total_bookings ?? 0,
                'total_spent' => $bookingStats->total_spent ?? 0,
            ]);

            $this->line("Updated Client ID: {$client->id} | Bookings: {$client->total_bookings} | Spent: {$client->total_spent}");
        }

        $this->info('All recalculations complete!');
    }
}
