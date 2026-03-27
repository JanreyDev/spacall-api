<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class UserAddress extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'user_id',
        'label',
        'street_address',
        'city',
        'province',
        'country',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $spatialFields = [
        'location',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
