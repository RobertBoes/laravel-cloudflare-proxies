<?php

use RobertBoes\CloudflareProxies\Ranges\CloudflareRanges;
use RobertBoes\CloudflareProxies\Ranges\Snapshot;

it('ships a snapshot in which every entry is a valid Cloudflare range', function () {
    $data = json_decode(file_get_contents(Snapshot::PATH), true, flags: JSON_THROW_ON_ERROR);

    $ranges = CloudflareRanges::fromArray($data);

    expect($ranges->ipv4)->not->toBeEmpty()
        ->and($ranges->ipv6)->not->toBeEmpty()
        ->and($ranges->fetchedAt)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});
