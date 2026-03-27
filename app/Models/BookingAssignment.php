<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id','provider_id','assignment_type','notified_at','viewed_at','responded_at','response','decline_reason','meta'];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
