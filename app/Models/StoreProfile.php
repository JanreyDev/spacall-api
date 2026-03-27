<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'store_name',
        'description',
        'address',
        'barangay',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'photos',
    ];

    protected $casts = [
        'photos' => 'array',
        'amenities' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(StoreTherapist::class, 'store_profile_id');
    }
}
