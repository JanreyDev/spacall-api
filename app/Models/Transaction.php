<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = ['transaction_id','transactable_type','transactable_id','type','amount','currency','status','meta','completed_at'];

    protected $casts = [
        'meta' => 'json',
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($t) {
            if (empty($t->transaction_id)) {
                $t->transaction_id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function transactable(): MorphTo
    {
        return $this->morphTo();
    }
}
