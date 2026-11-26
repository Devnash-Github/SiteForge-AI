<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    public const GEN_IDLE = 'idle';

    public const GEN_PENDING = 'pending';

    public const GEN_PROCESSING = 'processing';

    public const GEN_COMPLETED = 'completed';

    public const GEN_FAILED = 'failed';

    protected $fillable = [
        'name', 'slug', 'theme', 'status', 'generation_status', 'generation_error',
        'generation_mode', 'generation_preferences', 'published_content',
        'published_at', 'content_updated_at', 'generated_at', 'content_version', 'published_version',
    ];

    protected $hidden = ['published_content'];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'generation_preferences' => 'array',
            'published_content' => 'array',
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WebsiteSection::class)->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isGenerating(): bool
    {
        return in_array($this->generation_status, [self::GEN_PENDING, self::GEN_PROCESSING], true);
    }

    /** True when the editor content differs from the last published snapshot. */
    public function hasUnpublishedChanges(): bool
    {
        return $this->isPublished() && (int) $this->content_version > (int) $this->published_version;
    }

    public function touchContent(): void
    {
        $this->forceFill(['content_updated_at' => now(), 'content_version' => (int) $this->content_version + 1])->save();
    }

    public function publicUrl(): string
    {
        return route('sites.show', ['slug' => $this->slug]);
    }
}
