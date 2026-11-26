<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_PDF = 'pdf';

    public const TYPE_WEBSITE = 'website';

    public const TYPE_SOCIAL = 'social';

    public const TYPE_GOOGLE_BUSINESS = 'google_business';

    public const TYPE_IMAGE = 'image';

    public const TYPE_SEARCH_RESULT = 'search_result';

    public const TYPES = [
        self::TYPE_TEXT, self::TYPE_PDF, self::TYPE_WEBSITE, self::TYPE_SOCIAL,
        self::TYPE_GOOGLE_BUSINESS, self::TYPE_IMAGE, self::TYPE_SEARCH_RESULT,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BLOCKED = 'blocked';

    public const IDENTITY_MATCHED = 'matched';

    public const IDENTITY_UNCERTAIN = 'uncertain';

    public const IDENTITY_CONFIRMED = 'confirmed';

    public const IDENTITY_REJECTED = 'rejected';

    protected $fillable = [
        'type', 'label', 'url', 'file_path', 'file_name', 'mime_type',
        'extracted_content', 'status', 'error_message', 'identity_status',
        'metadata', 'processed_at',
    ];

    protected $hidden = ['extracted_content', 'file_path'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(BusinessFact::class);
    }

    public function isFetchable(): bool
    {
        return in_array($this->type, [self::TYPE_WEBSITE, self::TYPE_SOCIAL, self::TYPE_GOOGLE_BUSINESS, self::TYPE_SEARCH_RESULT], true)
            && filled($this->url);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /** Facts from this source may be trusted for auto-suggestion only when identity is not in doubt. */
    public function identityInDoubt(): bool
    {
        return $this->identity_status === self::IDENTITY_UNCERTAIN;
    }
}
