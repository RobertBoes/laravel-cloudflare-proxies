<?php

use RobertBoes\CloudflareProxies\Testing\SampleAddresses;

it('finds the first host in a range', function (string $cidr, string $host) {
    expect(SampleAddresses::firstHostIn($cidr))->toBe($host);
})->with([
    ['173.245.48.0/20', '173.245.48.1'],
    ['2606:4700::/32', '2606:4700::1'],
    ['203.0.113.7/32', '203.0.113.7'],
]);
