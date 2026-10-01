<?php

namespace RobertBoes\CloudflareProxies\Ranges;

use RobertBoes\CloudflareProxies\Exceptions\InvalidRanges;
use Symfony\Component\HttpFoundation\IpUtils;

final class RangeParser
{
    /**
     * Ranges no Cloudflare edge lives in. A list that touches one is refused,
     * because trusting it would skip real clients in the walk.
     */
    private const array RESERVED = [
        ...IpUtils::PRIVATE_SUBNETS,
        '224.0.0.0/4',
        'ff00::/8',
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
