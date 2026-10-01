<?php

namespace RobertBoes\CloudflareProxies;

use Illuminate\Contracts\Config\Repository;
use RobertBoes\CloudflareProxies\Ranges\CloudflareRanges;
use RobertBoes\CloudflareProxies\Ranges\RefreshedRanges;
use RobertBoes\CloudflareProxies\Ranges\Snapshot;

final readonly class ProxyList
{
    /**
     * Not Symfony's PRIVATE_SUBNETS: that also covers documentation ranges,
     * carrier-grade NAT and public IPv6 tunnel ranges, all of which hold real
     * clients that the walk must not skip.
     */
    public const array PRIVATE_NETWORKS = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '::1/128',
        'fc00::/7',
    ];

    public function __construct(
        private Repository $config,
        private RefreshedRanges $refreshed,
    ) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return [
            ...($this->config->get('cloudflare-proxies.cloudflare') ? $this->cloudflare()->all() : []),
            ...($this->config->get('cloudflare-proxies.private') ? self::PRIVATE_NETWORKS : []),
            ...$this->extra(),
        ];
    }

    /**
     * The bundled snapshot plus the cached refresh, when refresh is on.
     */
    public function cloudflare(): CloudflareRanges
    {
        $snapshot = Snapshot::load();

        if (! $this->config->get('cloudflare-proxies.refresh')) {
            return $snapshot;
        }

        $refreshed = $this->refreshed->get();

        return $refreshed === null ? $snapshot : $snapshot->with($refreshed);
    }

    /**
     * @return list<string>
     */
    public function extra(): array
    {
        $proxies = $this->config->get('cloudflare-proxies.proxies') ?? [];
        $proxies = is_string($proxies) ? explode(',', $proxies) : (array) $proxies;

        return array_values(array_filter(array_map(fn (mixed $proxy): string => trim((string) $proxy), $proxies)));
    }

    public function headers(): int
    {
        return (int) $this->config->get('cloudflare-proxies.headers');
    }
}
