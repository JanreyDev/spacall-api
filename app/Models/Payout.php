<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = ['payout_number','provider_id','amount','status','meta','paid_at'];

    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
