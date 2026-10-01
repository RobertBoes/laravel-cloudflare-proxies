<?php

namespace RobertBoes\CloudflareProxies\Ranges;

use Illuminate\Contracts\Cache\Factory;
use Throwable;

final readonly class RefreshedRanges
{
    public const string CACHE_KEY = 'cloudflare-proxies:ranges';

    public function __construct(private Factory $cache) {}

    /**
     * The last good refresh, validated again on the way out so a tampered or
     * corrupted cache entry cannot widen the trusted set. Never throws: a missing
     * or broken cache means the snapshot alone.
     */
    public function get(): ?CloudflareRanges
    {
        try {
            $cached = $this->cache->store()->get(self::CACHE_KEY);

            return is_array($cached) ? CloudflareRanges::fromArray($cached) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function put(CloudflareRanges $ranges): void
    {
        $this->cache->store()->forever(self::CACHE_KEY, $ranges->toArray());
    }
}
