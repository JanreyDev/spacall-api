<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class BookingLocation extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'booking_id',
        'address',
        'barangay',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'location',
        'distance_km',
        'landmark',
        'delivery_instructions',
    ];

    protected $spatialFields = [
        'location'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            unset($model->location);
            if (!isset($model->distance_km)) {
                $model->distance_km = 0;
            }
        });

        static::updating(function ($model) {
            unset($model->location);
        });
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
