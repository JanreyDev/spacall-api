<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTimeline extends Model
{
    use HasFactory;

    protected $table = 'booking_timeline';
    public $timestamps = false;
    protected $fillable = ['booking_id','status','notes','changed_by','created_at'];

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
