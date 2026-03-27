<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['wallet_id','transaction_id','amount','type','balance_after','description'];

    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
}
