<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    const TIER_CLASSIC = 'classic';
    const TIER_VIP = 'vip';
    const TIER_STORE = 'store';

    protected $fillable = [
        'booking_number',
        'customer_id',
        'provider_id',
        'service_id',
        'booking_type',
        'schedule_type',
        'customer_tier',
        'assignment_type',
        'scheduled_at',
        'duration_minutes',
        'status',
        'travel_mode',
        'accepted_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cancellation_fee',
        'service_price',
        'distance_km',
        'distance_surcharge',
        'subtotal',
        'platform_fee',
        'promo_discount',
        'total_amount',
        'payment_method',
        'payment_status',
        'customer_notes',
        'provider_notes',
        'gender_preference',
        'intensity_preference',
        'verification_code',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'service_price' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'distance_surcharge' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'promo_discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cancellation_fee' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }
    public function location(): HasOne
    {
        return $this->hasOne(BookingLocation::class);
    }
    public function timeline(): HasMany
    {
        return $this->hasMany(BookingTimeline::class);
    }

    /**
     * Return a standardized minimal array for transport stability.
     */
    public function toThinArray(): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'status' => $this->status,
            'travel_mode' => $this->travel_mode,
            'payment_status' => $this->payment_status,
            'total_amount' => $this->total_amount,
            'customer_notes' => $this->customer_notes,
            'duration_minutes' => $this->duration_minutes,
            'started_at' => $this->started_at ? $this->started_at->toIso8601String() : null,
            'accepted_at' => $this->accepted_at ? $this->accepted_at->toIso8601String() : null,
            'completed_at' => $this->completed_at ? $this->completed_at->toIso8601String() : null,
            'service' => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'base_price' => $this->service->base_price,
                'duration_minutes' => $this->service->duration_minutes,
            ] : null,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'first_name' => $this->customer->first_name,
                'last_name' => $this->customer->last_name,
                'mobile_number' => $this->customer->mobile_number,
                'wallet_balance' => $this->customer->wallet_balance,
                'profile_photo_url' => $this->customer->profile_photo_url,
            ] : null,
            'location' => $this->location ? [
                'id' => $this->location->id,
                'address' => str_replace(["\r", "\n"], ' ', $this->location->address),
                'latitude' => $this->location->latitude,
                'longitude' => $this->location->longitude,
            ] : null,
            'therapist' => $this->provider ? [
                'id' => $this->provider->id,
                'nickname' => ($this->provider->user && $this->provider->user->nickname)
                    ? $this->provider->user->nickname
                    : 'Therapist',
                'user' => $this->provider->user ? [
                    'profile_photo_url' => $this->provider->user->profile_photo_url,
                    'facial_scanner_photo_url' => $this->provider->user->id_selfie_photo_url,
                ] : null
            ] : null,
            'booking_type' => $this->booking_type,
            'schedule_type' => $this->schedule_type,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'gender_preference' => $this->gender_preference,
            'intensity_preference' => $this->intensity_preference,
            'verification_code' => $this->verification_code,
            'created_at' => $this->created_at,
        ];
    }
}
