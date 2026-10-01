<?php

namespace RobertBoes\CloudflareProxies\Testing;

use Illuminate\Contracts\Config\Repository;
use RobertBoes\CloudflareProxies\ProxyList;
use RobertBoes\CloudflareProxies\Testing\SampleAddresses as Sample;

/**
 * The requests that prove a setup: shared by the test helper, which sends them
 * through the app's middleware stack, and the check command.
 */
final readonly class Scenarios
{
    public function __construct(
        private ProxyList $proxies,
        private Repository $config,
    ) {}

    public function applicable(): bool
    {
        return (bool) $this->config->get('cloudflare-proxies.enabled')
            && (bool) $this->config->get('cloudflare-proxies.cloudflare');
    }

    /**
     * @return list<Scenario>
     */
    public function all(): array
    {
        $cloudflare = $this->proxies->cloudflare();
        $edgeV4 = Sample::firstHostIn($cloudflare->ipv4[0]);
        $edgeV6 = Sample::firstHostIn($cloudflare->ipv6[0]);
        $behindProxy = (bool) $this->config->get('cloudflare-proxies.private');

        // Behind a private proxy, Cloudflare's edge is the last X-Forwarded-For entry.
        // Without one, the edge is the remote address itself.
        $chain = fn (string $client, string $edge): array => $behindProxy
            ? [Sample::PRIVATE_HOP, Sample::SPOOFED.", {$client}, {$edge}"]
            : [$edge, Sample::SPOOFED.", {$client}"];

        [$hopV4, $chainV4] = $chain(Sample::CLIENT_V4, $edgeV4);
        [$hopV6, $chainV6] = $chain(Sample::CLIENT_V6, $edgeV6);

        return array_values(array_filter([
            new Scenario(
                'an untrusted remote address is used as is, and cannot claim https',
                Sample::UNTRUSTED_REMOTE,
                ['X-Forwarded-For' => Sample::SPOOFED, 'X-Forwarded-Proto' => 'https'],
                Sample::UNTRUSTED_REMOTE,
            ),
            $behindProxy ? new Scenario(
                'a private hop with a single entry resolves to that entry',
                Sample::PRIVATE_HOP,
                ['X-Forwarded-For' => Sample::CLIENT_V4],
                Sample::CLIENT_V4,
            ) : null,
            new Scenario(
                'a chain with a spoofed leading entry resolves to the address Cloudflare appended (IPv4)',
                $hopV4,
                ['X-Forwarded-For' => $chainV4],
                Sample::CLIENT_V4,
            ),
            new Scenario(
                'a chain with a spoofed leading entry resolves to the address Cloudflare appended (IPv6)',
                $hopV6,
                ['X-Forwarded-For' => $chainV6],
                Sample::CLIENT_V6,
            ),
            new Scenario(
                'X-Forwarded-Proto: https through the chain makes the request secure',
                $hopV4,
                ['X-Forwarded-For' => $chainV4, 'X-Forwarded-Proto' => 'https'],
                Sample::CLIENT_V4,
                expectedSecure: true,
            ),
            new Scenario(
                'a spoofed X-Forwarded-Host and X-Forwarded-Port are ignored',
                $hopV4,
                ['X-Forwarded-For' => $chainV4, 'X-Forwarded-Host' => Sample::SPOOFED_HOST, 'X-Forwarded-Port' => Sample::SPOOFED_PORT],
                Sample::CLIENT_V4,
            ),
        ]));
    }
}
