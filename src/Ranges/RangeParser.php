<?php

namespace RobertBoes\CloudflareProxies\Ranges;

use RobertBoes\CloudflareProxies\Exceptions\InvalidRanges;
use Symfony\Component\HttpFoundation\IpUtils;

final class RangeParser
{
    /**
     * Ranges no Cloudflare edge lives in. A list that touches one is refused,
     * because trusting it would skip real clients in the walk. Spelled out rather
     * than taken from Symfony's PRIVATE_SUBNETS, which is shorter in the Symfony
     * versions Laravel 12 allows (no carrier-grade NAT, no documentation ranges).
     */
    private const array RESERVED = [
        '0.0.0.0/8',       // "this network"
        '10.0.0.0/8',      // private
        '100.64.0.0/10',   // carrier-grade NAT
        '127.0.0.0/8',     // loopback
        '169.254.0.0/16',  // link-local
        '172.16.0.0/12',   // private
        '192.0.0.0/24',    // IETF protocol assignments
        '192.0.2.0/24',    // documentation
        '192.168.0.0/16',  // private
        '198.18.0.0/15',   // benchmarking
        '198.51.100.0/24', // documentation
        '203.0.113.0/24',  // documentation
        '224.0.0.0/4',     // multicast
        '240.0.0.0/4',     // reserved, and broadcast
        '::/96',           // unspecified and IPv4-compatible
        '::ffff:0:0/96',   // IPv4-mapped
        '64:ff9b::/96',    // NAT64
        '64:ff9b:1::/48',  // NAT64 local use
        '100::/64',        // discard
        '2001::/32',       // Teredo
        '2001:2::/48',     // benchmarking
        '2001:db8::/32',   // documentation
        '2002::/16',       // 6to4
        'fc00::/7',        // unique local
        'fe80::/10',       // link-local
        'ff00::/8',        // multicast
    ];

    /**
     * @return list<string>
     *
     * @throws InvalidRanges
     */
    public static function parse(string $body, IpFamily $family): array
    {
        $lines = preg_split('/\R/', trim($body)) ?: [];

        return self::validate(array_map(trim(...), $lines), $family);
    }

    /**
     * @param  array<mixed>  $ranges
     * @return list<string>
     *
     * @throws InvalidRanges
     */
    public static function validate(array $ranges, IpFamily $family): array
    {
        $ranges = array_values(array_filter($ranges, fn (mixed $range): bool => $range !== ''));

        if ($ranges === []) {
            throw InvalidRanges::empty($family);
        }

        foreach ($ranges as $range) {
            self::validateRange($range, $family);
        }

        /** @var list<string> $ranges */
        return $ranges;
    }

    private static function validateRange(mixed $range, IpFamily $family): void
    {
        if (! is_string($range) || ! preg_match('#^([0-9a-fA-F.:]+)/(\d{1,3})$#', $range, $matches)) {
            throw InvalidRanges::invalidEntry($family, is_string($range) ? $range : get_debug_type($range), 'is not a CIDR');
        }

        [, $address, $prefix] = $matches;

        if (filter_var($address, FILTER_VALIDATE_IP, $family->filterFlag()) === false) {
            throw InvalidRanges::invalidEntry($family, $range, "is not an {$family->value} range");
        }

        if ((int) $prefix > $family->longestPrefix()) {
            throw InvalidRanges::invalidEntry($family, $range, 'has a prefix longer than the address');
        }

        if ((int) $prefix < $family->widestPrefix()) {
            throw InvalidRanges::invalidEntry($family, $range, "is wider than /{$family->widestPrefix()}");
        }

        foreach (self::RESERVED as $reserved) {
            if (self::overlaps($range, $reserved)) {
                throw InvalidRanges::invalidEntry($family, $range, "overlaps the private or reserved range {$reserved}");
            }
        }
    }

    /**
     * Two CIDRs overlap exactly when one contains the other's network address.
     */
    private static function overlaps(string $range, string $other): bool
    {
        return IpUtils::checkIp(explode('/', $range)[0], $other)
            || IpUtils::checkIp(explode('/', $other)[0], $range);
    }
}
