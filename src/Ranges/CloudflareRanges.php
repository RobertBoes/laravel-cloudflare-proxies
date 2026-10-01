<?php

namespace RobertBoes\CloudflareProxies\Ranges;

use RobertBoes\CloudflareProxies\Exceptions\InvalidRanges;

final readonly class CloudflareRanges
{
    /**
     * @param  list<string>  $ipv4
     * @param  list<string>  $ipv6
     */
    public function __construct(
        public array $ipv4,
        public array $ipv6,
        public string $fetchedAt,
    ) {}

    /**
     * @param  array<mixed>  $data
     *
     * @throws InvalidRanges
     */
    public static function fromArray(array $data): self
    {
        if (! is_array($data['ipv4'] ?? null) || ! is_array($data['ipv6'] ?? null) || ! is_string($data['fetched_at'] ?? null)) {
            throw InvalidRanges::malformed('expected ipv4, ipv6 and fetched_at');
        }

        return new self(
            ipv4: RangeParser::validate($data['ipv4'], IpFamily::V4),
            ipv6: RangeParser::validate($data['ipv6'], IpFamily::V6),
            fetchedAt: $data['fetched_at'],
        );
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return [...$this->ipv4, ...$this->ipv6];
    }

    /**
     * The union of both lists: ranges can be added, never removed.
     */
    public function with(self $other): self
    {
        return new self(
            ipv4: array_values(array_unique([...$this->ipv4, ...$other->ipv4])),
            ipv6: array_values(array_unique([...$this->ipv6, ...$other->ipv6])),
            fetchedAt: $this->fetchedAt,
        );
    }

    /**
     * @return list<string>
     */
    public function missingFrom(self $other): array
    {
        return array_values(array_diff($this->all(), $other->all()));
    }

    public function sameRangesAs(self $other): bool
    {
        return $this->missingFrom($other) === [] && $other->missingFrom($this) === [];
    }

    /**
     * @return array{fetched_at: string, ipv4: list<string>, ipv6: list<string>}
     */
    public function toArray(): array
    {
        return [
            'fetched_at' => $this->fetchedAt,
            'ipv4' => $this->ipv4,
            'ipv6' => $this->ipv6,
        ];
    }
}
