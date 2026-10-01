<?php

namespace RobertBoes\CloudflareProxies\Ranges;

final class Snapshot
{
    public const string PATH = __DIR__.'/../../resources/cloudflare-ips.json';

    /**
     * The ranges shipped with this release. CI validates the file, so it is not
     * validated again on every boot.
     */
    public static function load(string $path = self::PATH): CloudflareRanges
    {
        /** @var array{fetched_at: string, ipv4: list<string>, ipv6: list<string>} $data */
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return new CloudflareRanges($data['ipv4'], $data['ipv6'], $data['fetched_at']);
    }

    public static function write(CloudflareRanges $ranges, string $path = self::PATH): void
    {
        file_put_contents($path, json_encode($ranges->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }
}
