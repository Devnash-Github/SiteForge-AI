<?php

namespace App\Services\Websites;

use App\Models\Business;
use App\Models\Media;
use Illuminate\Support\Collection;

/** Everything a generator may rely on: only owner input and confirmed facts. */
final class WebsiteContext
{
    /**
     * @param  array<string, string[]>  $facts  confirmed fact values by key
     * @param  Collection<int, Media>  $images
     */
    public function __construct(
        public readonly Business $business,
        public readonly array $facts,
        public readonly Collection $images,
        public readonly string $ownerNotes,
    ) {}

    /** @return string[] */
    public function values(string $key): array
    {
        return $this->facts[$key] ?? [];
    }

    public function first(string $key): string
    {
        return $this->facts[$key][0] ?? '';
    }

    /** @return string[] confirmed services followed by confirmed products */
    public function offerings(): array
    {
        return array_slice(array_values(array_unique(array_merge($this->values('service'), $this->values('product')))), 0, 12);
    }

    public function displayName(): string
    {
        return $this->business->name;
    }

    /** Text that factual claims in generated copy are allowed to rely on. */
    public function evidence(): string
    {
        $parts = [$this->business->name, $this->business->city, (string) $this->business->category, $this->ownerNotes];
        foreach ($this->facts as $values) {
            $parts = array_merge($parts, $values);
        }

        return implode("\n", array_filter($parts));
    }

    /** Structured, trusted summary sent to the AI. */
    public function toPromptData(): array
    {
        return [
            'business_name' => $this->business->name,
            'city' => $this->business->city,
            'category' => $this->business->category ?: ($this->first('category') ?: null),
            'confirmed_descriptions' => array_slice($this->values('description'), 0, 4),
            'confirmed_services' => $this->values('service'),
            'confirmed_products' => $this->values('product'),
            'confirmed_highlights' => $this->values('highlight'),
            'has_phone' => $this->values('phone') !== [],
            'has_email' => $this->values('email') !== [],
            'has_address' => $this->values('address') !== [],
            'has_opening_hours' => $this->values('opening_hours') !== [],
            'photo_count' => $this->images->count(),
        ];
    }
}
