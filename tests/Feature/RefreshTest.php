<?php

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RobertBoes\CloudflareProxies\ProxyList;
use RobertBoes\CloudflareProxies\Ranges\CloudflareRanges;
use RobertBoes\CloudflareProxies\Ranges\RangeFetcher;
use RobertBoes\CloudflareProxies\Ranges\RefreshedRanges;
use RobertBoes\CloudflareProxies\Ranges\Snapshot;

function fakeCloudflare(string $ipv4, string $ipv6 = "2606:4700::/32\n"): void
{
    Http::fake([
        RangeFetcher::IPV4_URL => Http::response($ipv4),
        RangeFetcher::IPV6_URL => Http::response($ipv6),
    ]);
}

it('is off by default and reads nothing from the cache', function () {
    expect(config('cloudflare-proxies.refresh'))->toBeFalse();

    Cache::shouldReceive('store')->never();

    app(ProxyList::class)->all();
});

it('refuses to run while refresh is off', function () {
    Http::preventStrayRequests();

    $this->artisan('cloudflare-proxies:refresh')
        ->expectsOutputToContain('Runtime refresh is off')
        ->assertFailed();
});

it('adds fetched ranges to the snapshot', function () {
    config()->set('cloudflare-proxies.refresh', true);
    fakeCloudflare("173.245.48.0/20\n45.64.64.0/22\n");

    $this->artisan('cloudflare-proxies:refresh')->assertSuccessful();

    expect(app(ProxyList::class)->all())
        ->toContain('45.64.64.0/22')
        ->toContain(...Snapshot::load()->all());
});

it('keeps the last good list when Cloudflare answers with something implausible', function (string $ipv4) {
    config()->set('cloudflare-proxies.refresh', true);
    app(RefreshedRanges::class)->put(new CloudflareRanges(['173.245.48.0/20', '45.64.64.0/22'], ['2606:4700::/32'], '2026-09-01'));
    fakeCloudflare($ipv4);

    $this->artisan('cloudflare-proxies:refresh')
        ->expectsOutputToContain('The last good list stays in use')
        ->assertFailed();

    expect(app(RefreshedRanges::class)->get()->ipv4)->toBe(['173.245.48.0/20', '45.64.64.0/22']);
})->with([
    'empty' => '',
    'HTML' => "<!DOCTYPE html>\n<html><body>Attention required</body></html>",
    'garbage' => 'not a range',
    'an IPv6 range in the IPv4 list' => '2606:4700::/32',
    'a private range' => '10.0.0.0/8',
    'carrier-grade NAT' => '100.64.0.0/10',
    'everything' => '0.0.0.0/0',
    'too wide' => '45.0.0.0/8',
    'a range containing a reserved one' => '192.0.0.0/12',
]);

it('rejects private and reserved IPv6 ranges', function (string $ipv6) {
    config()->set('cloudflare-proxies.refresh', true);
    fakeCloudflare("173.245.48.0/20\n", $ipv6);

    $this->artisan('cloudflare-proxies:refresh')->assertFailed();

    expect(app(RefreshedRanges::class)->get())->toBeNull();
})->with([
    'unique local' => 'fc00::/32',
    'Teredo' => '2001::/32',
    '6to4' => '2002::/32',
    'documentation' => '2001:db8::/32',
    'multicast' => 'ff00::/32',
]);

it('rejects an IPv6 list that is too wide', function () {
    config()->set('cloudflare-proxies.refresh', true);
    fakeCloudflare("173.245.48.0/20\n", "::/0\n");

    $this->artisan('cloudflare-proxies:refresh')->assertFailed();

    expect(app(RefreshedRanges::class)->get())->toBeNull();
});

it('fails clearly when Cloudflare cannot be reached', function () {
    config()->set('cloudflare-proxies.refresh', true);
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $this->artisan('cloudflare-proxies:refresh')
        ->expectsOutputToContain('Connection timed out')
        ->assertFailed();
});

it('falls back to the snapshot when the cache throws', function () {
    config()->set('cloudflare-proxies.refresh', true);

    $cache = Mockery::mock(Factory::class);
    $cache->shouldReceive('store')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));
    $proxies = new ProxyList(config(), new RefreshedRanges($cache));

    expect($proxies->cloudflare()->all())->toBe(Snapshot::load()->all());
});

it('ignores a tampered cache entry', function () {
    config()->set('cloudflare-proxies.refresh', true);
    Cache::forever(RefreshedRanges::CACHE_KEY, ['fetched_at' => '2026-10-01', 'ipv4' => ['0.0.0.0/0'], 'ipv6' => ['::/0']]);

    expect(app(ProxyList::class)->all())->not->toContain('0.0.0.0/0', '::/0');
});

it('fails clearly when the cache cannot be written', function () {
    config()->set('cloudflare-proxies.refresh', true);
    fakeCloudflare("173.245.48.0/20\n");
    Cache::shouldReceive('store->forever')->andThrow(new RuntimeException('no such table: cache'));

    $this->artisan('cloudflare-proxies:refresh')
        ->expectsOutputToContain('could not be stored in the cache')
        ->assertFailed();
});
