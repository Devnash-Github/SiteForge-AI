<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSection extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'content', 'position', 'is_visible'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_visible' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function toRenderer(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'visible' => $this->is_visible,
            'content' => $this->content,
        ];
    }
}
