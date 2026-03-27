<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = ['code','discount_type','discount_value','usage_limit','valid_from','valid_to','is_active','meta'];

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) return false;
        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_to && $now->gt($this->valid_to)) return false;
        if ($this->usage_limit && $this->usages()->count() >= $this->usage_limit) return false;
        return true;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($this->discount_type === 'amount') return (float) min($this->discount_value, $amount);
        return (float) round($amount * ($this->discount_value / 100), 2);
    }
}
