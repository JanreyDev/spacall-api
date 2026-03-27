<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreTherapist extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_profile_id',
        'name',
        'bio',
        'years_of_experience',
        'profile_photo_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'years_of_experience' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(StoreProfile::class, 'store_profile_id');
    }
}
