<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'uuid', 'disk', 'file_path', 'file_name', 'mime_type', 'size',
        'width', 'height', 'alt_text', 'metadata',
    ];

    protected $hidden = ['file_path', 'disk'];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function getUrlAttribute(): string
    {
        return route('media.show', ['uuid' => $this->uuid]);
    }

    public function toRendererAsset(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'alt' => $this->alt_text ?: pathinfo($this->file_name, PATHINFO_FILENAME),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
