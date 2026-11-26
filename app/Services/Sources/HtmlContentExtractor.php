<?php

namespace App\Services\Sources;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns raw HTML into readable text plus structured hints (JSON-LD business
 * data, tel:/mailto: links, metadata). Never executes anything from the page.
 */
class HtmlContentExtractor
{
    private const BUSINESS_TYPE_HINTS = ['business', 'store', 'restaurant', 'organization', 'organisation', 'cafe', 'salon', 'shop', 'clinic', 'dentist', 'physician', 'hotel', 'service', 'agency', 'office', 'bakery', 'gym', 'spa', 'school', 'center', 'centre', 'place'];

    /**
     * @return array{title: ?string, description: ?string, site_name: ?string, text: string, headings: string[], phones: string[], emails: string[], structured: array<string, mixed>, links: string[]}
     */
    public function extract(string $html, string $baseUrl): array
    {
        $html = $this->toUtf8($html);

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $meta = fn (string $q) => $this->firstAttr($xpath, $q, 'content');

        $structured = $this->structuredData($xpath);

        $phones = [];
        $emails = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            if (stripos($href, 'tel:') === 0) {
                $phones[] = trim(urldecode(substr($href, 4)));
            } elseif (stripos($href, 'mailto:') === 0) {
                $email = strtolower(trim(strtok(urldecode(substr($href, 7)), '?')));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $email;
                }
            }
        }

        $links = $this->interestingLinks($xpath, $baseUrl);

        foreach (['script', 'style', 'noscript', 'svg', 'iframe', 'template', 'canvas', 'form'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $headings = [];
        foreach ($xpath->query('//h1|//h2|//h3') as $h) {
            $t = $this->clean($h->textContent);
            if ($t !== '' && mb_strlen($t) < 160) {
                $headings[] = $t;
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $text = $body ? $this->blockText($body) : '';

        $titleNode = $dom->getElementsByTagName('title')->item(0);

        return [
            'title' => $this->nullIfEmpty($meta('//meta[@property="og:title"]') ?? ($titleNode ? $this->clean($titleNode->textContent) : null)),
            'description' => $this->nullIfEmpty($meta('//meta[@name="description"]') ?? $meta('//meta[@property="og:description"]')),
            'site_name' => $this->nullIfEmpty($meta('//meta[@property="og:site_name"]')),
            'text' => mb_substr($text, 0, 20000),
            'headings' => array_slice(array_values(array_unique($headings)), 0, 40),
            'phones' => array_values(array_unique(array_filter($phones))),
            'emails' => array_values(array_unique($emails)),
            'structured' => $structured,
            'links' => $links,
        ];
    }

    /** Builds the plain-text representation stored as the source's extracted content. */
    public function toDocument(array $extracted): string
    {
        $parts = [];
        foreach (['title' => 'Page title', 'site_name' => 'Site name', 'description' => 'Page description'] as $k => $label) {
            if (! empty($extracted[$k])) {
                $parts[] = "{$label}: {$extracted[$k]}";
            }
        }
        $s = $extracted['structured'] ?? [];
        foreach (['name' => 'Listed name', 'telephone' => 'Listed phone', 'email' => 'Listed email', 'address' => 'Listed address', 'opening_hours' => 'Listed opening hours', 'description' => 'Listed description'] as $k => $label) {
            if (! empty($s[$k])) {
                $parts[] = "{$label}: {$s[$k]}";
            }
        }
        if (! empty($extracted['phones'])) {
            $parts[] = 'Phone links: '.implode(', ', $extracted['phones']);
        }
        if (! empty($extracted['emails'])) {
            $parts[] = 'Email links: '.implode(', ', $extracted['emails']);
        }
        if (! empty($extracted['text'])) {
            $parts[] = "\n".$extracted['text'];
        }

        return trim(implode("\n", $parts));
    }

    /** @return array<string, string> */
    private function structuredData(DOMXPath $xpath): array
    {
        $found = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $json = json_decode(trim($script->textContent), true);
            if (! is_array($json)) {
                continue;
            }
            foreach ($this->flattenLd($json) as $node) {
                $types = array_map('strtolower', (array) ($node['@type'] ?? []));
                $isBusiness = false;
                foreach ($types as $type) {
                    foreach (self::BUSINESS_TYPE_HINTS as $hint) {
                        if (str_contains($type, $hint)) {
                            $isBusiness = true;
                        }
                    }
                }
                if (! $isBusiness || (empty($node['telephone']) && empty($node['address']) && empty($node['email']))) {
                    continue;
                }
                $found = array_filter([
                    'name' => is_string($node['name'] ?? null) ? $this->clean($node['name']) : null,
                    'telephone' => is_string($node['telephone'] ?? null) ? $this->clean($node['telephone']) : null,
                    'email' => is_string($node['email'] ?? null) ? strtolower(str_replace('mailto:', '', $this->clean($node['email']))) : null,
                    'address' => $this->addressString($node['address'] ?? null),
                    'opening_hours' => $this->hoursString($node),
                    'description' => is_string($node['description'] ?? null) ? mb_substr($this->clean($node['description']), 0, 1500) : null,
                ]);
                break 2;
            }
        }

        return $found;
    }

    private function flattenLd(array $json): array
    {
        $nodes = [];
        if (isset($json['@graph']) && is_array($json['@graph'])) {
            foreach ($json['@graph'] as $n) {
                if (is_array($n)) {
                    $nodes[] = $n;
                }
            }
        } elseif (array_is_list($json)) {
            foreach ($json as $n) {
                if (is_array($n)) {
                    $nodes = array_merge($nodes, $this->flattenLd($n));
                }
            }
        } else {
            $nodes[] = $json;
        }

        return $nodes;
    }

    private function addressString(mixed $address): ?string
    {
        if (is_string($address)) {
            return $this->nullIfEmpty($this->clean($address));
        }
        if (is_array($address)) {
            if (array_is_list($address)) {
                $address = $address[0] ?? [];
                if (! is_array($address)) {
                    return null;
                }
            }
            $parts = [];
            foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $k) {
                $v = $address[$k] ?? null;
                if (is_array($v)) {
                    $v = $v['name'] ?? null;
                }
                if (is_string($v) && trim($v) !== '') {
                    $parts[] = $this->clean($v);
                }
            }

            return $parts ? implode(', ', array_unique($parts)) : null;
        }

        return null;
    }

    private function hoursString(array $node): ?string
    {
        $hours = $node['openingHours'] ?? null;
        if (is_string($hours)) {
            return $this->clean($hours);
        }
        if (is_array($hours) && array_is_list($hours)) {
            return implode('; ', array_filter(array_map(fn ($h) => is_string($h) ? $this->clean($h) : null, $hours)));
        }
        $spec = $node['openingHoursSpecification'] ?? null;
        if (is_array($spec)) {
            $spec = array_is_list($spec) ? $spec : [$spec];
            $lines = [];
            foreach ($spec as $s) {
                if (! is_array($s)) {
                    continue;
                }
                $days = array_map(fn ($d) => is_string($d) ? basename(str_replace('http://schema.org/', '', $d)) : '', (array) ($s['dayOfWeek'] ?? []));
                $lines[] = trim(implode(', ', array_filter($days)).' '.($s['opens'] ?? '').'–'.($s['closes'] ?? ''));
            }

            return $lines ? implode('; ', $lines) : null;
        }

        return null;
    }

    /** Same-site links that usually hold business details (about, contact, services, menu). */
    private function interestingLinks(DOMXPath $xpath, string $baseUrl): array
    {
        $baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $found = [];
        foreach ($xpath->query('//a[@href]') as $a) {
            /** @var DOMElement $a */
            $href = trim($a->getAttribute('href'));
            $label = strtolower($this->clean($a->textContent).' '.$href);
            if (! preg_match('/\b(about|contact|services?|menu|pricing|prices|products?|treatments|reach-us|our-story)\b/', $label)) {
                continue;
            }
            if (preg_match('#^(mailto:|tel:|javascript:|\#)#i', $href)) {
                continue;
            }
            $absolute = app(UrlFetcher::class)->resolveUrl($baseUrl, $href);
            $absolute = strtok($absolute, '#');
            if (strtolower((string) parse_url($absolute, PHP_URL_HOST)) !== $baseHost) {
                continue;
            }
            $found[$absolute] = true;
        }

        return array_slice(array_keys($found), 0, 10);
    }

    private function blockText(\DOMNode $node): string
    {
        $blocks = ['p', 'div', 'section', 'article', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'footer', 'br', 'address', 'td', 'main', 'aside', 'nav', 'ul', 'ol', 'table', 'dd', 'dt'];
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $out .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $isBlock = in_array(strtolower($child->nodeName), $blocks, true);
                $out .= ($isBlock ? "\n" : '').$this->blockText($child).($isBlock ? "\n" : '');
            }
        }
        $lines = array_map(fn ($l) => $this->clean($l), explode("\n", $out));

        return implode("\n", array_values(array_filter($lines, fn ($l) => $l !== '')));
    }

    private function firstAttr(DOMXPath $xpath, string $query, string $attr): ?string
    {
        $node = $xpath->query($query)->item(0);

        return $node instanceof DOMElement ? $this->clean($node->getAttribute($attr)) : null;
    }

    private function clean(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function nullIfEmpty(?string $v): ?string
    {
        return $v === null || trim($v) === '' ? null : $v;
    }

    private function toUtf8(string $html): string
    {
        if (! mb_check_encoding($html, 'UTF-8')) {
            $converted = @mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1, Windows-1252');
            $html = $converted !== false ? $converted : $html;
        }

        return $html;
    }
}
