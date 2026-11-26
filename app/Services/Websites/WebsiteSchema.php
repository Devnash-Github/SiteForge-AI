<?php

namespace App\Services\Websites;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The single source of truth for website content. AI output, editor saves,
 * previews and published snapshots all pass through this validator, so the
 * renderer only ever receives known section types with plain-text fields.
 */
class WebsiteSchema
{
    public const SECTION_TYPES = ['hero', 'about', 'services', 'gallery', 'contact', 'footer'];

    /** Sections that may appear only once per website. */
    public const UNIQUE_TYPES = ['hero', 'about', 'services', 'gallery', 'contact', 'footer'];

    public const SECTION_LABELS = [
        'hero' => 'Hero', 'about' => 'About', 'services' => 'Services',
        'gallery' => 'Gallery', 'contact' => 'Contact', 'footer' => 'Footer',
    ];

    public static function defaultContent(string $type, string $businessName = ''): array
    {
        return match ($type) {
            'hero' => ['headline' => $businessName ?: 'Welcome', 'subheadline' => '', 'cta_text' => 'Get in touch', 'cta_url' => '#contact', 'image_id' => null],
            'about' => ['heading' => 'About us', 'body' => '', 'highlights' => [], 'image_id' => null],
            'services' => ['title' => 'What we offer', 'intro' => '', 'items' => []],
            'gallery' => ['title' => 'Gallery', 'image_ids' => []],
            'contact' => ['heading' => 'Contact us', 'intro' => '', 'phone' => '', 'email' => '', 'address' => '', 'hours' => '', 'website' => '', 'cta_text' => 'Call us'],
            'footer' => ['business_name' => $businessName, 'tagline' => '', 'show_contact' => true],
            default => throw ValidationException::withMessages(['type' => 'Unsupported section type.']),
        };
    }

    /**
     * Validate and sanitise one section's content.
     *
     * @param  int[]|null  $allowedMediaIds  media ids the business owns (null skips the check)
     */
    public function section(string $type, mixed $content, ?array $allowedMediaIds = null, string $errorPrefix = 'content'): array
    {
        if (! in_array($type, self::SECTION_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported section type: '.Str::limit($type, 30)]);
        }
        if (! is_array($content)) {
            throw ValidationException::withMessages([$errorPrefix => 'Section content must be an object.']);
        }

        $errors = [];
        $out = match ($type) {
            'hero' => [
                'headline' => $this->text($content, 'headline', 120),
                'subheadline' => $this->text($content, 'subheadline', 300),
                'cta_text' => $this->text($content, 'cta_text', 40),
                'cta_url' => $this->ctaUrl($content['cta_url'] ?? '', $errors, "{$errorPrefix}.cta_url"),
                'image_id' => $this->mediaId($content['image_id'] ?? null, $allowedMediaIds, $errors, "{$errorPrefix}.image_id"),
            ],
            'about' => [
                'heading' => $this->text($content, 'heading', 100),
                'body' => $this->text($content, 'body', 2000, multiline: true),
                'highlights' => $this->stringList($content['highlights'] ?? [], 6, 120),
                'image_id' => $this->mediaId($content['image_id'] ?? null, $allowedMediaIds, $errors, "{$errorPrefix}.image_id"),
            ],
            'services' => [
                'title' => $this->text($content, 'title', 100),
                'intro' => $this->text($content, 'intro', 400),
                'items' => $this->serviceItems($content['items'] ?? []),
            ],
            'gallery' => [
                'title' => $this->text($content, 'title', 100),
                'image_ids' => $this->mediaIds($content['image_ids'] ?? [], $allowedMediaIds, $errors, "{$errorPrefix}.image_ids"),
            ],
            'contact' => [
                'heading' => $this->text($content, 'heading', 100),
                'intro' => $this->text($content, 'intro', 300),
                'phone' => $this->phone($content['phone'] ?? '', $errors, "{$errorPrefix}.phone"),
                'email' => $this->email($content['email'] ?? '', $errors, "{$errorPrefix}.email"),
                'address' => $this->text($content, 'address', 300, multiline: true),
                'hours' => $this->text($content, 'hours', 400, multiline: true),
                'website' => $this->webUrl($content['website'] ?? '', $errors, "{$errorPrefix}.website"),
                'cta_text' => $this->text($content, 'cta_text', 40),
            ],
            'footer' => [
                'business_name' => $this->text($content, 'business_name', 160),
                'tagline' => $this->text($content, 'tagline', 200),
                'show_contact' => (bool) ($content['show_contact'] ?? true),
            ],
        };

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    public function theme(mixed $theme): array
    {
        if (! is_array($theme)) {
            throw ValidationException::withMessages(['theme' => 'Theme must be an object.']);
        }
        $default = ThemePresets::default();
        $errors = [];
        $out = ['preset' => in_array($theme['preset'] ?? null, [...array_keys(ThemePresets::PRESETS), 'custom'], true) ? $theme['preset'] : 'custom'];

        foreach (['primary', 'secondary', 'background', 'text'] as $color) {
            $value = $theme[$color] ?? $default[$color];
            if (! is_string($value) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $errors["theme.{$color}"] = 'Colors must be hex values like #4F46E5.';
            }
            $out[$color] = is_string($value) ? strtoupper($value) : $default[$color];
        }

        $out['font'] = in_array($theme['font'] ?? null, ThemePresets::FONTS, true) ? $theme['font'] : null;
        $out['button_style'] = in_array($theme['button_style'] ?? null, ThemePresets::BUTTON_STYLES, true) ? $theme['button_style'] : null;
        if ($out['font'] === null) {
            $errors['theme.font'] = 'Choose a supported font.';
        }
        if ($out['button_style'] === null) {
            $errors['theme.button_style'] = 'Choose a supported button style.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Plain text: tags stripped, control characters removed, whitespace normalised, length capped. */
    public function plain(mixed $value, int $max, bool $multiline = false): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = str_replace("\r", '', $value);
        $value = $multiline
            ? trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $value) ?? '') ?? '')
            : trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, $max);
    }

    private function text(array $content, string $key, int $max, bool $multiline = false): string
    {
        return $this->plain($content[$key] ?? '', $max, $multiline);
    }

    private function stringList(mixed $items, int $maxItems, int $maxLen): array
    {
        if (! is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            $v = $this->plain($item, $maxLen);
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, $maxItems);
    }

    private function serviceItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }
        $out = [];
        foreach (array_slice($items, 0, 24) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = $this->plain($item['name'] ?? '', 120);
            if ($name === '') {
                continue;
            }
            $out[] = ['name' => $name, 'description' => $this->plain($item['description'] ?? '', 400)];
        }

        return $out;
    }

    private function ctaUrl(mixed $value, array &$errors, string $field): string
    {
        $value = $this->plain($value, 500);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#[a-z0-9-]{1,40}$/i', $value)) {
            return $value;
        }
        if (preg_match('/^tel:\+?[0-9 ()-]{5,20}$/', $value)) {
            return $value;
        }
        if (preg_match('/^mailto:/i', $value) && filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
        if (preg_match('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        $errors[$field] = 'Button link must be a web address (https://…), a phone link (tel:…), an email link (mailto:…) or a section like #contact.';

        return '';
    }

    private function webUrl(mixed $value, array &$errors, string $field): string
    {
        $value = $this->plain($value, 500);
        if ($value === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }
        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $errors[$field] = 'Enter a valid website address.';

            return '';
        }

        return $value;
    }

    private function phone(mixed $value, array &$errors, string $field): string
    {
        $value = $this->plain($value, 40);
        if ($value !== '' && ! preg_match('/^\+?[0-9 ()\/.-]{6,25}$/', $value)) {
            $errors[$field] = 'Enter a valid phone number.';
        }

        return $value;
    }

    private function email(mixed $value, array &$errors, string $field): string
    {
        $value = Str::lower($this->plain($value, 190));
        if ($value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = 'Enter a valid email address.';
        }

        return $value;
    }

    private function mediaId(mixed $value, ?array $allowed, array &$errors, string $field): ?int
    {
        if ($value === null || $value === '' || $value === 0) {
            return null;
        }
        if (! is_numeric($value)) {
            $errors[$field] = 'Invalid image.';

            return null;
        }
        $id = (int) $value;
        if ($allowed !== null && ! in_array($id, $allowed, true)) {
            $errors[$field] = 'That image does not belong to this business.';

            return null;
        }

        return $id;
    }

    private function mediaIds(mixed $values, ?array $allowed, array &$errors, string $field): array
    {
        if (! is_array($values)) {
            return [];
        }
        $out = [];
        foreach (array_slice($values, 0, 24) as $v) {
            $id = $this->mediaId($v, $allowed, $errors, $field);
            if ($id !== null) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
