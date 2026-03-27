<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $booking;
    public int $targetProviderId;

    /**
     * Create a new event instance.
     * @param \App\Models\Booking $booking
     * @param int|null $targetProviderId Override which therapist channel to broadcast on.
     *                                   Defaults to the booking's own provider_id.
     */
    public function __construct(\App\Models\Booking $booking, ?int $targetProviderId = null)
    {
        $this->booking = $booking->load(['customer', 'service', 'location']);
        // Use override if provided (for browsable bookings), else use the booking's assigned provider
        $this->targetProviderId = $targetProviderId ?? $booking->provider_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('therapist.' . $this->targetProviderId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'booking' => $this->booking,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'BookingRequested';
    }
}
