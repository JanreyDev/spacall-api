<?php

namespace App\Events;

use App\Models\SupportMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(SupportMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.session.' . $this->message->support_session_id),
            new PrivateChannel('admin.support'), // Channel for all admins to see new support messages
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'sender_id' => $this->message->sender_id,
                'sender_name' => $this->message->sender->first_name . ' ' . $this->message->sender->last_name,
                'sender_avatar' => $this->message->sender->profile_photo_url,
                'sender_role' => $this->message->sender->role,
                'support_session_id' => $this->message->support_session_id,
                'created_at' => $this->message->created_at->toIso8601String(),
            ]
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'SupportMessageSent';
    }
}
