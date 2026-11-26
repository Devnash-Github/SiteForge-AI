<?php

namespace App\Services\Websites;

/**
 * Writes neutral copy without AI, using only owner input and confirmed facts.
 * Used when Gemini is not configured or when the owner asks for it.
 */
class BasicCopyWriter
{
    public function write(WebsiteContext $ctx): array
    {
        $business = $ctx->business;
        $category = $business->category ?: $ctx->first('category');
        $description = $business->description ?: $ctx->first('description');
        $firstSentence = $description ? trim((string) (preg_split('/(?<=[.!?])\s+/u', $description)[0] ?? '')) : '';

        $subheadline = $firstSentence !== '' && mb_strlen($firstSentence) <= 220
            ? $firstSentence
            : ($category ? ucfirst($category).' in '.$business->city : 'Based in '.$business->city);

        return [
            'hero' => ['headline' => $business->name, 'subheadline' => $subheadline],
            'about' => ['heading' => 'About '.$business->name, 'body' => '', 'highlights' => $ctx->values('highlight')],
            'services' => [
                'title' => $ctx->values('service') !== [] ? 'Our services' : 'Our products',
                'intro' => '',
                'items' => array_map(fn ($s) => ['name' => $s, 'description' => ''], $ctx->offerings()),
            ],
            'gallery' => ['title' => 'Gallery'],
            'contact' => ['heading' => 'Get in touch', 'intro' => 'We would be glad to hear from you.'],
            'footer' => ['tagline' => $business->city],
        ];
    }
}
