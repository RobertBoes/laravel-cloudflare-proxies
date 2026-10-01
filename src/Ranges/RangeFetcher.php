<?php

namespace RobertBoes\CloudflareProxies\Ranges;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use RobertBoes\CloudflareProxies\Exceptions\InvalidRanges;

final readonly class RangeFetcher
{
    public const string IPV4_URL = 'https://www.cloudflare.com/ips-v4';

    public const string IPV6_URL = 'https://www.cloudflare.com/ips-v6';

    public function __construct(private Factory $http) {}

    /**
     * @throws InvalidRanges when Cloudflare's answer is not a plausible list
     * @throws ConnectionException
     * @throws RequestException
     */
    public function fetch(): CloudflareRanges
    {
        return new CloudflareRanges(
            ipv4: RangeParser::parse($this->download(self::IPV4_URL), IpFamily::V4),
            ipv6: RangeParser::parse($this->download(self::IPV6_URL), IpFamily::V6),
            fetchedAt: date('Y-m-d'),
        );
    }

    private function download(string $url): string
    {
        return $this->http
            ->withUserAgent('robertboes/laravel-cloudflare-proxies')
            ->timeout(10)
            ->get($url)
            ->throw()
            ->body();
    }
}
