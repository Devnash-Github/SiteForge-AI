<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessFact extends Model
{
    use HasFactory;

    public const STATUS_EXTRACTED = 'extracted';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_EXTRACTED, self::STATUS_NEEDS_REVIEW, self::STATUS_CONFIRMED, self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'source_id', 'source_type', 'key', 'value', 'normalized_value', 'status', 'extracted_at',
    ];

    protected function casts(): array
    {
        return ['extracted_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
