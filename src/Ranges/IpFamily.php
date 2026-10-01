<?php

namespace RobertBoes\CloudflareProxies\Ranges;

enum IpFamily: string
{
    case V4 = 'IPv4';
    case V6 = 'IPv6';

    public function filterFlag(): int
    {
        return match ($this) {
            self::V4 => FILTER_FLAG_IPV4,
            self::V6 => FILTER_FLAG_IPV6,
        };
    }

    public function longestPrefix(): int
    {
        return match ($this) {
            self::V4 => 32,
            self::V6 => 128,
        };
    }

    /**
     * The widest range a refresh accepts. Cloudflare's widest today are /13 and /29.
     */
    public function widestPrefix(): int
    {
        return match ($this) {
            self::V4 => 12,
            self::V6 => 28,
        };
    }
}
