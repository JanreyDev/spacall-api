<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'recipient_id',
        'booking_id',
        'content',
        'attachments',
        'is_read',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_read' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
