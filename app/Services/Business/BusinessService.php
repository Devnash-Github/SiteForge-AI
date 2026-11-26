<?php

namespace App\Services\Business;

use App\Jobs\ProcessSourceJob;
use App\Jobs\ResearchBusinessJob;
use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\Source;
use App\Models\User;
use App\Models\Website;
use App\Services\Facts\FactNormalizer;
use App\Services\Sources\MediaService;
use App\Services\Sources\SourceProcessingService;
use App\Services\Websites\WebsiteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class BusinessService
{
    public const LINK_FIELDS = [
        'website_url' => Source::TYPE_WEBSITE,
        'instagram_url' => Source::TYPE_SOCIAL,
        'facebook_url' => Source::TYPE_SOCIAL,
        'linkedin_url' => Source::TYPE_SOCIAL,
        'google_business_url' => Source::TYPE_GOOGLE_BUSINESS,
    ];

    public function __construct(
        private MediaService $media,
        private WebsiteService $websites,
        private FactNormalizer $normalizer,
    ) {}

    /**
     * Create a business with its sources, uploads and a draft website,
     * then queue processing and research.
     *
     * @param  UploadedFile[]  $files
     * @return array{business: Business, website: Website}
     */
    public function create(User $user, array $data, array $files = []): array
    {
        $jobs = [];

        [$business, $website] = DB::transaction(function () use ($user, $data, $files, &$jobs) {
            $business = $user->businesses()->create([
                ...$this->attributes($data),
                'status' => Business::STATUS_RESEARCHING,
                'research_status' => 'pending',
            ]);

            $this->syncOwnerFacts($business);
            $sources = $this->createOwnerTextSource($business) ? [$business->sources()->latest('id')->first()] : [];
            foreach ($this->linkSources($business, array_keys(self::LINK_FIELDS)) as $source) {
                $sources[] = $source;
            }
            foreach ($this->otherUrlSources($business, $business->other_urls ?? []) as $source) {
                $sources[] = $source;
            }
            foreach ($this->fileSources($business, $files) as $source) {
                $sources[] = $source;
            }

            $website = $this->websites->createForBusiness($business);

            foreach ($sources as $source) {
                $jobs[] = new ProcessSourceJob($source->id);
            }
            $jobs[] = new ResearchBusinessJob($business->id);

            return [$business, $website];
        });

        // Research runs after the owner's own sources so it can use their phone numbers for identity checks.
        Bus::chain($jobs)->dispatch();

        return ['business' => $business, 'website' => $website];
    }

    public function update(Business $business, array $data): Business
    {
        $before = $business->only(array_merge(array_keys(self::LINK_FIELDS), ['description', 'additional_info', 'other_urls']));

        $jobs = DB::transaction(function () use ($business, $data, $before) {
            $business->update($this->attributes($data));
            $this->syncOwnerFacts($business);
            $jobs = [];

            if ($business->description !== $before['description'] || $business->additional_info !== $before['additional_info']) {
                $source = $business->sources()->where('type', Source::TYPE_TEXT)->where('metadata->trusted', true)->first();
                $content = $this->ownerText($business);
                if ($source && $content !== '') {
                    $source->update(['extracted_content' => $content, 'status' => Source::STATUS_PENDING]);
                    $jobs[] = new ProcessSourceJob($source->id);
                } elseif (! $source && $this->createOwnerTextSource($business)) {
                    $jobs[] = new ProcessSourceJob($business->sources()->latest('id')->value('id'));
                }
            }

            $changed = array_filter(array_keys(self::LINK_FIELDS), fn ($f) => filled($business->{$f}) && $business->{$f} !== $before[$f]);
            foreach ($this->linkSources($business, $changed) as $source) {
                $jobs[] = new ProcessSourceJob($source->id);
            }
            $newOther = array_values(array_diff($business->other_urls ?? [], $before['other_urls'] ?? []));
            foreach ($this->otherUrlSources($business, $newOther) as $source) {
                $jobs[] = new ProcessSourceJob($source->id);
            }

            return $jobs;
        });

        if ($jobs !== []) {
            $business->forceFill(['status' => Business::STATUS_RESEARCHING])->save();
            Bus::chain($jobs)->dispatch();
        }

        return $business;
    }

    /**
     * Add sources later from the research page.
     *
     * @param  UploadedFile[]  $files
     * @return Source[]
     */
    public function addSources(Business $business, ?string $url, ?string $text, array $files = []): array
    {
        $sources = DB::transaction(function () use ($business, $url, $text, $files) {
            $sources = [];
            if (filled($url)) {
                $sources = array_merge($sources, $this->otherUrlSources($business, [$url]));
            }
            if (filled($text)) {
                $sources[] = $business->sources()->create([
                    'type' => Source::TYPE_TEXT,
                    'label' => 'Additional information',
                    'extracted_content' => mb_substr(trim($text), 0, 20000),
                    'status' => Source::STATUS_PENDING,
                    'metadata' => ['trusted' => true],
                ]);
            }

            return array_merge($sources, $this->fileSources($business, $files));
        });

        if ($sources !== []) {
            $business->forceFill(['status' => Business::STATUS_RESEARCHING])->save();
            Bus::chain(array_map(fn ($s) => new ProcessSourceJob($s->id), $sources))->dispatch();
        }

        return $sources;
    }

    public function delete(Business $business): void
    {
        $this->media->deleteBusinessFiles($business);
        $business->delete();
    }

    public function ownerText(Business $business): string
    {
        return trim(implode("\n\n", array_filter([$business->description, $business->additional_info])));
    }

    private function attributes(array $data): array
    {
        $attrs = collect($data)->only([
            'name', 'city', 'category', 'description', 'additional_info',
            'website_url', 'instagram_url', 'facebook_url', 'linkedin_url', 'google_business_url',
        ])->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();

        if (array_key_exists('other_urls', $data)) {
            $urls = is_array($data['other_urls']) ? $data['other_urls'] : preg_split('/[\s,]+/', (string) $data['other_urls']);
            $attrs['other_urls'] = array_values(array_unique(array_filter(array_map('trim', $urls ?: []))));
        }

        return $attrs;
    }

    /** The name the owner typed is a confirmed fact by definition. */
    private function syncOwnerFacts(Business $business): void
    {
        $business->facts()->where('source_type', 'owner')->where('key', 'business_name')->delete();
        $business->facts()->create([
            'source_id' => null,
            'source_type' => 'owner',
            'key' => 'business_name',
            'value' => $business->name,
            'normalized_value' => $this->normalizer->normalize('business_name', $business->name),
            'status' => BusinessFact::STATUS_CONFIRMED,
            'extracted_at' => now(),
        ]);
        if ($business->category) {
            $business->facts()->where('source_type', 'owner')->where('key', 'category')->delete();
            $business->facts()->create([
                'source_id' => null, 'source_type' => 'owner', 'key' => 'category', 'value' => $business->category,
                'normalized_value' => $this->normalizer->normalize('category', $business->category),
                'status' => BusinessFact::STATUS_CONFIRMED, 'extracted_at' => now(),
            ]);
        }
    }

    private function createOwnerTextSource(Business $business): bool
    {
        $content = $this->ownerText($business);
        if ($content === '') {
            return false;
        }
        $business->sources()->create([
            'type' => Source::TYPE_TEXT,
            'label' => 'Information you entered',
            'extracted_content' => $content,
            'status' => Source::STATUS_PENDING,
            'metadata' => ['trusted' => true],
        ]);

        return true;
    }

    /** @return Source[] */
    private function linkSources(Business $business, array $fields): array
    {
        $labels = ['website_url' => 'Website', 'instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'linkedin_url' => 'LinkedIn', 'google_business_url' => 'Google Business Profile'];
        $sources = [];
        foreach ($fields as $field) {
            $url = $business->{$field};
            if (! filled($url)) {
                continue;
            }
            $sources[] = $business->sources()->create([
                'type' => self::LINK_FIELDS[$field],
                'label' => $labels[$field],
                'url' => $url,
                'status' => Source::STATUS_PENDING,
                'metadata' => ['field' => $field],
            ]);
        }

        return $sources;
    }

    /** @return Source[] */
    private function otherUrlSources(Business $business, array $urls): array
    {
        $sources = [];
        foreach (array_slice($urls, 0, 10) as $url) {
            $type = SourceProcessingService::typeForUrl($url);
            $sources[] = $business->sources()->create([
                'type' => $type,
                'label' => (string) (parse_url($url, PHP_URL_HOST) ?: 'Link'),
                'url' => $url,
                'status' => Source::STATUS_PENDING,
            ]);
        }

        return $sources;
    }

    /**
     * @param  UploadedFile[]  $files
     * @return Source[]
     */
    private function fileSources(Business $business, array $files): array
    {
        $sources = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if ($file->getMimeType() === 'application/pdf') {
                $stored = $this->media->storeDocument($business, $file);
                $sources[] = $business->sources()->create([
                    'type' => Source::TYPE_PDF,
                    'label' => $stored['name'],
                    'file_path' => $stored['path'],
                    'file_name' => $stored['name'],
                    'mime_type' => 'application/pdf',
                    'status' => Source::STATUS_PENDING,
                    'metadata' => ['disk' => $stored['disk'], 'size' => $file->getSize()],
                ]);
            } else {
                $media = $this->media->storeImage($business, $file);
                $sources[] = $business->sources()->create([
                    'type' => Source::TYPE_IMAGE,
                    'label' => $media->file_name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'status' => Source::STATUS_PENDING,
                    'metadata' => ['media_id' => $media->id],
                ]);
            }
        }

        return $sources;
    }
}
