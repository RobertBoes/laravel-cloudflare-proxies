<?php

use RobertBoes\CloudflareProxies\ProxyList;
use RobertBoes\CloudflareProxies\Testing\SampleAddresses;
use Symfony\Component\HttpFoundation\IpUtils;

beforeEach(fn () => $this->configureProxies(['cloudflare' => true, 'private' => true]));

it('does not contain the sample addresses the scenarios rely on', function (string $address) {
    expect(IpUtils::checkIp($address, app(ProxyList::class)->all()))->toBeFalse();
})->with(SampleAddresses::outsideTheTrustedSet());

it('does not contain addresses where real clients live', function (string $address) {
    expect(IpUtils::checkIp($address, app(ProxyList::class)->all()))->toBeFalse();
})->with([
    'carrier-grade NAT (Tailscale)' => '100.64.0.1',
    'Teredo' => '2001::1',
    '6to4' => '2002::1',
    'documentation' => '198.51.100.7',
    'link-local' => '169.254.1.1',
]);

it('contains the private hop and a Cloudflare edge', function (string $address) {
    expect(IpUtils::checkIp($address, app(ProxyList::class)->all()))->toBeTrue();
})->with([
    SampleAddresses::PRIVATE_HOP,
    '173.245.48.1',
    '2606:4700::1',
]);
