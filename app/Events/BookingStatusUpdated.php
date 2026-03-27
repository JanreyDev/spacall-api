<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $booking;

    /**
     * Create a new event instance.
     */
    public function __construct(\App\Models\Booking $booking)
    {
        $this->booking = $booking->load(['customer', 'service', 'location', 'provider.user']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('booking.' . $this->booking->id),
            new PrivateChannel('App.Models.User.' . $this->booking->customer_id),
        ];

        if ($this->booking->provider_id && $this->booking->provider && $this->booking->provider->user_id) {
            $channels[] = new PrivateChannel('App.Models.User.' . $this->booking->provider->user_id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'booking' => $this->booking->toThinArray(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'BookingStatusUpdated';
    }
}
