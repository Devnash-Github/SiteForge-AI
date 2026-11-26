<?php

namespace App\Services\Sources;

use App\Exceptions\SourceBlockedException;
use App\Exceptions\SourceProcessingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Fetches a public web page safely: SSRF checks on every redirect hop,
 * size and time limits, and honest detection of login walls / bot blocks.
 */
class UrlFetcher
{
    private const BLOCK_STATUSES = [401, 403, 407, 429, 451, 999];

    private const LOGIN_PATH_PATTERNS = [
        '#/login#i', '#/accounts/login#i', '#/signin#i', '#/sign-in#i', '#/checkpoint#i',
        '#/authwall#i', '#/uas/login#i', '#/challenge#i', '#consent\.google#i',
    ];

    public function __construct(private UrlGuard $guard) {}

    /**
     * @return array{url: string, status: int, content_type: string, body: string}
     */
    public function fetch(string $url): array
    {
        $maxRedirects = (int) config('siteforge.fetch.max_redirects', 4);
        $current = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $target = $this->guard->check($current);

            if ($this->looksLikeLoginPage($current)) {
                throw new SourceBlockedException('This page requires signing in, so its content is not publicly accessible.');
            }

            $options = ['allow_redirects' => false];
            if ($target['ip'] !== null && ! filter_var($target['host'], FILTER_VALIDATE_IP)) {
                // Pin DNS to the address we validated (prevents DNS-rebinding).
                $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
                $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]];
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('siteforge.fetch.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
                    'Accept-Language' => 'en-IN,en;q=0.9',
                ])
                    ->timeout((int) config('siteforge.fetch.timeout', 15))
                    ->connectTimeout(8)
                    ->withOptions($options)
                    ->get($current);
            } catch (ConnectionException) {
                throw new SourceProcessingException('The website could not be reached (connection failed or timed out).');
            }

            $status = $response->status();

            if ($status >= 300 && $status < 400 && $response->header('Location')) {
                $current = $this->resolveUrl($current, $response->header('Location'));

                continue;
            }

            if (in_array($status, self::BLOCK_STATUSES, true)) {
                throw new SourceBlockedException("The website refused automated access (HTTP {$status}).");
            }

            if ($status === 404 || $status === 410) {
                throw new SourceProcessingException('The page was not found (HTTP '.$status.').');
            }

            if ($status >= 400) {
                throw new SourceProcessingException("The website returned an error (HTTP {$status}).");
            }

            $contentType = strtolower($response->header('Content-Type') ?: 'text/html');
            if (! str_contains($contentType, 'html') && ! str_contains($contentType, 'text/plain')) {
                throw new SourceProcessingException('The link does not point to a web page (content type: '.strtok($contentType, ';').').');
            }

            $body = $response->body();
            $maxBytes = (int) config('siteforge.fetch.max_bytes', 2 * 1024 * 1024);
            if (strlen($body) > $maxBytes) {
                $body = substr($body, 0, $maxBytes);
            }

            return ['url' => $current, 'status' => $status, 'content_type' => $contentType, 'body' => $body];
        }

        throw new SourceProcessingException('The website redirected too many times.');
    }

    public function looksLikeLoginPage(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH).'?'.(string) parse_url($url, PHP_URL_QUERY);
        $host = (string) parse_url($url, PHP_URL_HOST);
        foreach (self::LOGIN_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $host.$path)) {
                return true;
            }
        }

        return false;
    }

    public function resolveUrl(string $base, string $relative): string
    {
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }
        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if (str_starts_with($relative, '//')) {
            return $scheme.':'.$relative;
        }
        if (str_starts_with($relative, '/')) {
            return "{$scheme}://{$host}{$port}{$relative}";
        }
        $dir = rtrim(dirname($parts['path'] ?? '/').'/', '/').'/';
        if ($dir === '\\/' || $dir === '//') {
            $dir = '/';
        }

        return "{$scheme}://{$host}{$port}".$dir.$relative;
    }
}
