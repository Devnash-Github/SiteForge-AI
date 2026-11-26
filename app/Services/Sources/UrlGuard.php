<?php

namespace App\Services\Sources;

use App\Exceptions\SourceProcessingException;

/**
 * Protects outbound fetching from SSRF: only http(s) URLs on standard ports
 * that resolve to public IP addresses are allowed.
 */
class UrlGuard
{
    /**
     * @return array{url: string, host: string, port: int, ip: ?string}
     */
    public function check(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new SourceProcessingException('This link is not a valid web address.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SourceProcessingException('Only http and https links can be processed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new SourceProcessingException('Links containing credentials are not allowed.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, [80, 443, 8080, 8443], true)) {
            throw new SourceProcessingException('This link uses a port that is not allowed.');
        }

        if (config('siteforge.fetch.allow_private_hosts')) {
            return ['url' => $url, 'host' => $host, 'port' => $port, 'ip' => null];
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new SourceProcessingException('This link points to a private address and cannot be fetched.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            throw new SourceProcessingException('The website address could not be found (DNS lookup failed).');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new SourceProcessingException('This link points to a private address and cannot be fetched.');
            }
        }

        return ['url' => $url, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    public function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /** @return string[] */
    protected function resolve(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        foreach ($records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }
        if ($ips === []) {
            $resolved = gethostbynamel($host);
            $ips = $resolved ?: [];
        }

        return array_values(array_unique($ips));
    }
}
