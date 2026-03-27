<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderService extends Model
{
    use HasFactory;

    protected $table = 'provider_services';
    protected $fillable = [
        'provider_id', 'service_id', 'price', 'is_available', 
        'home_service_enabled', 'store_service_enabled', 
        'base_distance_km', 'per_km_surcharge', 'max_travel_distance_km',
        'meta'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'per_km_surcharge' => 'decimal:2',
        'is_available' => 'boolean',
        'home_service_enabled' => 'boolean',
        'store_service_enabled' => 'boolean',
        'meta' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
