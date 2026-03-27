<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DispatchScheduledBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:dispatch-scheduled-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scheduled bookings to therapists 10 minutes before the session starts.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $threshold = $now->copy()->addMinutes(10);

        $bookings = \App\Models\Booking::where('schedule_type', 'scheduled')
            ->where('is_dispatched', false)
            ->where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $threshold)
            ->where('scheduled_at', '>', $now->subHours(1)) // Don't dispatch very old ones if they somehow stayed pending
            ->get();

        if ($bookings->isEmpty()) {
            return;
        }

        $this->info("Dispatching " . $bookings->count() . " scheduled bookings...");

        /** @var \App\Models\Booking $booking */
        foreach ($bookings as $booking) {
            try {
                // Determine if it's a direct booking or browsable
                if ($booking->provider_id) {
                    // Direct booking: just notify the assigned therapist
                    event(new \App\Events\BookingRequested($booking));
                } else {
                    // Browsable booking: transition status and broadcast to everyone available
                    $booking->status = 'awaiting_assignment';

                    $onlineProviders = \App\Models\Provider::where('is_available', true)
                        ->where('is_active', true)
                        ->get();

                    foreach ($onlineProviders as $nearbyProvider) {
                        event(new \App\Events\BookingRequested($booking, $nearbyProvider->id));
                    }
                }

                // Mark as dispatched
                $booking->is_dispatched = true;
                $booking->save();

                $this->info("Dispatched booking ID: {$booking->id}");
            } catch (\Exception $e) {
                $this->error("Failed to dispatch booking ID: {$booking->id}. Error: " . $e->getMessage());
            }
        }
    }
}
