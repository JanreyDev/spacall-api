<?php

namespace App\Observers;

use App\Models\Booking;
use App\Models\Provider;

class BookingObserver
{
    /**
     * Handle the Booking "created" event.
     */
    public function created(Booking $booking): void
    {
        $this->updateProviderStats($booking->provider_id);
        $this->updateCustomerStats($booking->customer_id);
    }

    /**
     * Handle the Booking "updated" event.
     */
    public function updated(Booking $booking): void
    {
        // If provider changed, update both
        if ($booking->isDirty('provider_id')) {
            $this->updateProviderStats($booking->getOriginal('provider_id'));
        }
        $this->updateProviderStats($booking->provider_id);
        $this->updateCustomerStats($booking->customer_id);
    }

    /**
     * Handle the Booking "deleted" event.
     */
    public function deleted(Booking $booking): void
    {
        $this->updateProviderStats($booking->provider_id);
        $this->updateCustomerStats($booking->customer_id);
    }

    /**
     * Recalculate and update provider stats based on bookings.
     */
    protected function updateProviderStats($providerId): void
    {
        if (!$providerId) return;

        $provider = Provider::find($providerId);
        if (!$provider) return;

        $totalBookings = Booking::where('provider_id', $providerId)
            ->where('status', '!=', 'cancelled')
            ->count();

        $completedBookings = Booking::where('provider_id', $providerId)
            ->where('status', 'completed')
            ->count();

        $totalEarnings = Booking::where('provider_id', $providerId)
            ->where('status', 'completed')
            ->sum('total_amount');

        $provider->update([
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'total_earnings' => $totalEarnings,
        ]);
    }

    /**
     * Recalculate and update customer stats based on bookings.
     */
    protected function updateCustomerStats($customerId): void
    {
        if (!$customerId) return;

        $user = \App\Models\User::find($customerId);
        if (!$user) return;

        $totalBookings = Booking::where('customer_id', $customerId)
            ->where('status', '!=', 'cancelled')
            ->count();

        $totalSpent = Booking::where('customer_id', $customerId)
            ->where('status', 'completed')
            ->sum('total_amount');

        $user->update([
            'total_bookings' => $totalBookings,
            'total_spent' => $totalSpent,
        ]);
    }
}
