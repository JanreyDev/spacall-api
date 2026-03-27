<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TherapistAvailabilityChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $therapistId;
    public bool $isAvailable;

    /**
     * Create a new event instance.
     */
    public function __construct(int $therapistId, bool $isAvailable)
    {
        $this->therapistId = $therapistId;
        $this->isAvailable = $isAvailable;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('therapists.availability'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'therapist_id' => $this->therapistId,
            'is_available' => $this->isAvailable,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'TherapistAvailabilityChanged';
    }
}
