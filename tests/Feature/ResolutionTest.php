<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use RobertBoes\CloudflareProxies\Testing\SampleAddresses as Sample;

it('resolves the client behind Cloudflare and a private proxy')->expectCloudflareProxiesToWork();

it('resolves the client behind Cloudflare without a private proxy', function () {
    $this->configureProxies(['private' => false]);

    $this->expectCloudflareProxiesToWork();
});

it('trusts extra proxies from config', function () {
    $this->configureProxies(['proxies' => '203.0.113.10, 203.0.113.11']);

    expect($this->client('203.0.113.11', ['X-Forwarded-For' => Sample::CLIENT_V4])['ip'])->toBe(Sample::CLIENT_V4);
});

it('trusts nothing extra when the extra proxies are empty', function () {
    $this->configureProxies(['proxies' => '']);

    expect($this->client('203.0.113.11', ['X-Forwarded-For' => Sample::CLIENT_V4])['ip'])->toBe('203.0.113.11');
});

it('leaves the trusted proxies alone when disabled', function () {
    $this->configureProxies(['enabled' => false]);

    expect($this->client(Sample::PRIVATE_HOP, ['X-Forwarded-For' => Sample::CLIENT_V4])['ip'])->toBe(Sample::PRIVATE_HOP);
});

it('ignores X-Forwarded-Host and X-Forwarded-Port by default', function () {
    $client = $this->client(Sample::PRIVATE_HOP, [
        'X-Forwarded-For' => Sample::CLIENT_V4,
        'X-Forwarded-Host' => Sample::SPOOFED_HOST,
        'X-Forwarded-Port' => Sample::SPOOFED_PORT,
    ]);

    expect($client['host'])->toBe('localhost')
        ->and($client['port'])->toBe(80);
});

it('trusts the headers configured', function () {
    $this->configureProxies(['headers' => Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST]);

    expect($this->client(Sample::PRIVATE_HOP, ['X-Forwarded-Host' => 'app.example'])['host'])->toBe('app.example');
});

it('never makes a network call during a request', function () {
    Http::preventStrayRequests();
    $this->configureProxies(['refresh' => true]);

    expect($this->client(Sample::PRIVATE_HOP, ['X-Forwarded-For' => Sample::CLIENT_V4])['ip'])->toBe(Sample::CLIENT_V4);
});

it('fails the helper when the app trusts every hop', function () {
    TrustProxies::at(['0.0.0.0/0', '::/0']);

    $this->expectCloudflareProxiesToWork();
})->throws(AssertionFailedError::class, 'The client IP is wrong');

it('fails the helper when the app trusts X-Forwarded-Host', function () {
    $this->configureProxies(['headers' => Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST]);

    $this->expectCloudflareProxiesToWork();
})->throws(AssertionFailedError::class, 'A spoofed X-Forwarded-Host was trusted');
