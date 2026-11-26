<?php

namespace App\Services\Sources;

use App\Exceptions\SourceProcessingException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfTextExtractor
{
    /**
     * @return array{text: string, pages: int, truncated: bool}
     */
    public function extract(string $absolutePath): array
    {
        if (! is_file($absolutePath) || filesize($absolutePath) === 0) {
            throw new SourceProcessingException('The PDF file is empty.');
        }

        $handle = fopen($absolutePath, 'rb');
        $header = $handle ? fread($handle, 1024) : '';
        if ($handle) {
            fclose($handle);
        }
        if (! str_contains((string) $header, '%PDF-')) {
            throw new SourceProcessingException('This file is not a valid PDF.');
        }

        try {
            $config = new Config;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(64 * 1024 * 1024);
            $document = (new Parser([], $config))->parseFile($absolutePath);
            $pages = $document->getPages();
        } catch (Throwable $e) {
            report($e);
            throw new SourceProcessingException('The PDF could not be read. It may be corrupt or password-protected.');
        }

        $maxPages = (int) config('siteforge.pdf.max_pages', 60);
        $maxChars = (int) config('siteforge.pdf.max_chars', 60000);
        $text = '';
        $truncated = count($pages) > $maxPages;

        foreach (array_slice($pages, 0, $maxPages) as $page) {
            try {
                $text .= $page->getText()."\n\n";
            } catch (Throwable) {
                continue; // one broken page should not lose the rest of the document
            }
            if (mb_strlen($text) > $maxChars) {
                $truncated = true;
                break;
            }
        }

        $text = trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\n{3,}/u", "\n\n", $text) ?? '') ?? '');
        $text = mb_substr($text, 0, $maxChars);

        if (mb_strlen(preg_replace('/\s+/u', '', $text) ?? '') < 20) {
            throw new SourceProcessingException('No readable text was found in this PDF. It may be a scanned image — please type the key details manually.');
        }

        return ['text' => $text, 'pages' => count($pages), 'truncated' => $truncated];
    }
}
