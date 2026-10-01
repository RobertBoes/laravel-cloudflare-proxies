<?php

namespace RobertBoes\CloudflareProxies\Testing;

final readonly class Scenario
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $description,
        public string $remoteAddress,
        public array $headers,
        public string $expectedIp,
        public bool $expectedSecure = false,
    ) {}
}
