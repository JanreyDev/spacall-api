<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'type',
        'file_path',
        'file_name',
        'mime_type',
        'uploaded_at',
    ];

    protected $appends = ['file_url'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function getFileUrlAttribute()
    {
        if (!$this->file_path) {
            return null;
        }
        if (str_starts_with($this->file_path, 'http')) {
            return $this->file_path;
        }
        return url('storage/' . $this->file_path);
    }
}
