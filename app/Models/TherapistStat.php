<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'total_online_minutes',
        'total_extensions',
        'total_bookings',
        'last_online_at',
    ];

    protected $casts = [
        'total_online_minutes' => 'integer',
        'total_extensions' => 'integer',
        'total_bookings' => 'integer',
        'last_online_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
