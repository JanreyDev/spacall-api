<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_session_id',
        'sender_id',
        'content',
        'attachments',
        'is_read',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_read' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportSession::class, 'support_session_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
