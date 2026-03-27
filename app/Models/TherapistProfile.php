<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class TherapistProfile extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'provider_id',
        'specializations',
        'bio',
        'years_of_experience',
        'certifications',
        'languages_spoken',
        'professional_photo_url',
        'license_number',
        'license_type',
        'license_expiry_date',
        'base_rate',
        'service_radius_km',
        'base_location_latitude',
        'base_location_longitude',
        'base_location',
        'base_address',
        'default_schedule',
        'has_own_equipment',
        'equipment_list',
        'gallery_images',
        'vip_status',
        'vip_applied_at',
    ];

    protected $spatialFields = [
        'base_location'
    ];

    protected $casts = [
        'specializations' => 'array',
        'certifications' => 'array',
        'languages_spoken' => 'array',
        'default_schedule' => 'array',
        'has_own_equipment' => 'boolean',
        'equipment_list' => 'array',
        'gallery_images' => 'array',
        'base_rate' => 'decimal:2',
        'license_expiry_date' => 'date',
        'vip_applied_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function getGalleryImagesAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // $value is already an array due to casts
        $images = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($images)) {
            return [];
        }

        return array_map(function ($image) {
            if (str_starts_with($image, 'http')) {
                return $image;
            }
            // Ensure proper slash formatting
            $path = ltrim($image, '/');
            // Check if it already starts with storage/
            if (str_starts_with($path, 'storage/')) {
                return url($path);
            }
            return url('storage/' . $path);
        }, $images);
    }
}
