<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name','slug','description','icon_url','sort_order','is_active'];

    protected static function booted(): void
    {
        static::creating(function ($cat) {
            if (empty($cat->slug) && !empty($cat->name)) {
                $cat->slug = Str::slug($cat->name);
            }
        });
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
