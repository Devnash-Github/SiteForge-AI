<?php

namespace App\Services\Websites;

use App\Services\Facts\FactNormalizer;

/**
 * Combines written copy (from AI or the basic writer) with facts that are
 * filled in deterministically — contact details, images, services list —
 * so factual fields never depend on model output.
 */
class SiteAssembler
{
    public function __construct(
        private WebsiteSchema $schema,
        private ClaimGuard $claims,
        private FactNormalizer $normalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $copy
     * @return array{sections: array<int, array{type: string, content: array, is_visible: bool}>, removed_claims: string[]}
     */
    public function assemble(WebsiteContext $ctx, array $copy): array
    {
        $removed = [];
        $evidence = $ctx->evidence();
        $guard = function (string $text) use ($evidence, &$removed): string {
            $r = $this->claims->filter($text, $evidence);
            array_push($removed, ...$r['removed']);

            return $r['text'];
        };

        $phone = $ctx->first('phone');
        $email = $ctx->first('email');
        $images = $ctx->images->pluck('id')->all();
        $heroImage = $images[0] ?? null;
        $aboutImage = count($images) >= 3 ? $images[1] : null;
        $galleryImages = count($images) > 4 ? array_values(array_diff($images, [$heroImage])) : $images;

        $ctaUrl = $phone !== '' ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : ($email !== '' ? 'mailto:'.$email : '#contact');
        $defaultCta = $phone !== '' ? 'Call now' : 'Get in touch';

        $headline = $guard((string) data_get($copy, 'hero.headline', '')) ?: $ctx->displayName();

        $offerings = $ctx->offerings();
        $serviceItems = $this->matchServiceItems($offerings, (array) data_get($copy, 'services.items', []), $guard);
        $highlights = $this->matchAllowed($ctx->values('highlight'), (array) data_get($copy, 'about.highlights', []));
        if ($highlights === []) {
            $highlights = array_slice($ctx->values('highlight'), 0, 4);
        }

        $aboutBody = $guard((string) data_get($copy, 'about.body', ''));
        if ($aboutBody === '') {
            $aboutBody = $this->neutralAbout($ctx);
        }

        $hasContact = $phone !== '' || $email !== '' || $ctx->first('address') !== '' || $ctx->first('opening_hours') !== '' || filled($ctx->business->website_url);

        $sections = [
            ['type' => 'hero', 'is_visible' => true, 'content' => [
                'headline' => $headline,
                'subheadline' => $guard((string) data_get($copy, 'hero.subheadline', '')),
                'cta_text' => $this->schema->plain(data_get($copy, 'hero.cta_text', ''), 40) ?: $defaultCta,
                'cta_url' => $ctaUrl,
                'image_id' => $heroImage,
            ]],
            ['type' => 'about', 'is_visible' => true, 'content' => [
                'heading' => $guard((string) data_get($copy, 'about.heading', '')) ?: 'About '.$ctx->displayName(),
                'body' => $aboutBody,
                'highlights' => $highlights,
                'image_id' => $aboutImage,
            ]],
            ['type' => 'services', 'is_visible' => $serviceItems !== [], 'content' => [
                'title' => $guard((string) data_get($copy, 'services.title', '')) ?: ($ctx->values('service') !== [] ? 'Our services' : 'Our products'),
                'intro' => $guard((string) data_get($copy, 'services.intro', '')),
                'items' => $serviceItems,
            ]],
            ['type' => 'gallery', 'is_visible' => $galleryImages !== [], 'content' => [
                'title' => $guard((string) data_get($copy, 'gallery.title', '')) ?: 'Gallery',
                'image_ids' => array_slice($galleryImages, 0, 12),
            ]],
            ['type' => 'contact', 'is_visible' => $hasContact, 'content' => [
                'heading' => $guard((string) data_get($copy, 'contact.heading', '')) ?: 'Get in touch',
                'intro' => $guard((string) data_get($copy, 'contact.intro', '')),
                'phone' => $phone,
                'email' => $email,
                'address' => $ctx->first('address'),
                'hours' => $ctx->first('opening_hours'),
                'website' => (string) $ctx->business->website_url,
                'cta_text' => $this->schema->plain(data_get($copy, 'contact.cta_text', ''), 40) ?: ($phone !== '' ? 'Call us' : ($email !== '' ? 'Email us' : '')),
            ]],
            ['type' => 'footer', 'is_visible' => true, 'content' => [
                'business_name' => $ctx->displayName(),
                'tagline' => $guard((string) data_get($copy, 'footer.tagline', '')),
                'show_contact' => true,
            ]],
        ];

        $allowedMedia = $images;
        foreach ($sections as &$section) {
            $section['content'] = $this->schema->section($section['type'], $section['content'], $allowedMedia);
        }

        return ['sections' => $sections, 'removed_claims' => array_values(array_unique($removed))];
    }

    /**
     * Keep only services the business actually offers; AI may describe them
     * but cannot add new ones. Every confirmed offering is listed.
     *
     * @param  string[]  $allowed
     */
    public function matchServiceItems(array $allowed, array $aiItems, callable $guard): array
    {
        $descriptions = [];
        foreach ($aiItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $match = $this->bestMatch((string) ($item['name'] ?? ''), $allowed);
            if ($match !== null && ! isset($descriptions[$match])) {
                $descriptions[$match] = $guard($this->schema->plain($item['description'] ?? '', 400));
            }
        }

        return array_map(fn ($name) => ['name' => $name, 'description' => $descriptions[$name] ?? ''], $allowed);
    }

    /** @param string[] $allowed */
    public function matchAllowed(array $allowed, array $proposed): array
    {
        $out = [];
        foreach ($proposed as $p) {
            if (is_string($p) && ($m = $this->bestMatch($p, $allowed)) !== null) {
                $out[] = $m;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 6);
    }

    /** @param string[] $allowed */
    public function bestMatch(string $candidate, array $allowed): ?string
    {
        $c = $this->normalizer->words($candidate);
        if ($c === '') {
            return null;
        }
        foreach ($allowed as $a) {
            if ($this->normalizer->words($a) === $c) {
                return $a;
            }
        }
        foreach ($allowed as $a) {
            $aw = $this->normalizer->words($a);
            if ($aw !== '' && (str_contains($aw, $c) || str_contains($c, $aw))) {
                return $a;
            }
        }

        return null;
    }

    public function neutralAbout(WebsiteContext $ctx): string
    {
        if ($ctx->business->description) {
            return $this->schema->plain($ctx->business->description, 2000, true);
        }
        if ($ctx->first('description') !== '') {
            return $ctx->first('description');
        }
        $category = $ctx->business->category ?: $ctx->first('category');

        return $category
            ? "{$ctx->displayName()} is a {$category} based in {$ctx->business->city}."
            : "{$ctx->displayName()} is based in {$ctx->business->city}.";
    }
}
