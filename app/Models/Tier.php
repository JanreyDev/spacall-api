<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'level',
        'online_minutes_required',
        'extensions_required',
        'bookings_required',
    ];

    protected $casts = [
        'level' => 'integer',
        'online_minutes_required' => 'integer',
        'extensions_required' => 'integer',
        'bookings_required' => 'integer',
    ];
}
