<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RESEARCHING = 'researching';

    public const STATUS_READY = 'ready';

    protected $fillable = [
        'name', 'city', 'category', 'description', 'additional_info',
        'website_url', 'instagram_url', 'facebook_url', 'linkedin_url',
        'google_business_url', 'other_urls', 'status',
        'research_status', 'research_error', 'researched_at',
    ];

    protected function casts(): array
    {
        return [
            'other_urls' => 'array',
            'researched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(BusinessFact::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    /** Social / public links entered by the owner, keyed by platform. */
    public function socialLinks(): array
    {
        return array_filter([
            'instagram' => $this->instagram_url,
            'facebook' => $this->facebook_url,
            'linkedin' => $this->linkedin_url,
        ]);
    }
}
