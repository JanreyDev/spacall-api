<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, HasRoles;

    const TIER_CLASSIC = 'classic';
    const TIER_VIP = 'vip';
    const TIER_STORE = 'store';

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_DECLINED = 'declined';
    const STATUS_SUSPENDED = 'suspended';

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = self::STATUS_ACTIVE;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'mobile_number',
        'email',
        'first_name',
        'middle_name',
        'last_name',
        'nickname',
        'gender',
        'age',
        'date_of_birth',
        'profile_photo_url',
        'id_card_photo_url',
        'id_card_back_photo_url',
        'id_selfie_photo_url',
        'license_photo_url',
        'pin_hash',
        'is_verified',
        'status',
        'role',
        'wallet_balance',
        'customer_tier',
        'total_bookings',
        'total_spent',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'pin_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'date_of_birth' => 'date',
        'wallet_balance' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'total_bookings' => 'integer',
    ];

    /**
     * Relations
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function provider()
    {
        return $this->hasOne(Provider::class);
    }
    public function getProfilePhotoUrlAttribute($value)
    {
        return $this->formatPhotoUrl($value, true);
    }

    public function getIdCardPhotoUrlAttribute($value)
    {
        return $this->formatPhotoUrl($value);
    }

    public function getIdCardBackPhotoUrlAttribute($value)
    {
        return $this->formatPhotoUrl($value);
    }

    public function getIdSelfiePhotoUrlAttribute($value)
    {
        return $this->formatPhotoUrl($value);
    }

    public function getLicensePhotoUrlAttribute($value)
    {
        return $this->formatPhotoUrl($value);
    }

    private function formatPhotoUrl($value, $useDefaultAvatar = false)
    {
        if ($value) {
            if (str_starts_with($value, 'http')) {
                return $value;
            }
            // Clean leading slash
            $path = ltrim($value, '/');

            // Check if it already starts with storage/
            if (str_starts_with($path, 'storage/')) {
                return url($path);
            }

            return url('storage/' . $path);
        }

        if ($useDefaultAvatar) {
            $name = trim($this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name);
            return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=7F9CF5&background=EBF4FF';
        }

        return null;
    }

}
