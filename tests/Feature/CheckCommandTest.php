<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use RobertBoes\CloudflareProxies\Support\EffectiveTrustProxies;

it('passes when the package owns the trusted proxies', function () {
    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain('resolve as expected')
        ->assertSuccessful();
});

it('fails on a wildcard', function (string $wildcard) {
    TrustProxies::at($wildcard);

    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain("Trusted proxies are set to '{$wildcard}'")
        ->assertFailed();
})->with(['*', '**']);

it('fails when the app sets its own trusted proxies', function () {
    TrustProxies::at(['10.0.0.0/8']);

    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain('changed after this package set them')
        ->assertFailed();
});

it('sees trustProxies(at: ...) from bootstrap/app.php, which only runs when the HTTP kernel resolves', function () {
    // withMiddleware() registers its callback this way; forgetting the instance makes
    // the command's own resolution the first one, as it is under artisan.
    app()->forgetInstance(Kernel::class);
    app()->afterResolving(Kernel::class, fn () => (new Middleware)->trustProxies(at: '*'));

    expect(EffectiveTrustProxies::proxies())->not->toBe('*');

    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain("Trusted proxies are set to '*'")
        ->assertFailed();
});

it('fails when the app sets its own trusted headers', function () {
    TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_AWS_ELB);

    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain('trusted headers were changed')
        ->assertFailed();
});

it('fails when config/trustedproxy.php sets proxies', function () {
    config()->set('trustedproxy.proxies', '*');

    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain('config/trustedproxy.php')
        ->assertFailed();
});

it('warns but passes when disabled', function () {
    $this->configureProxies(['enabled' => false]);

    $this->artisan('cloudflare-proxies:check')
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();
});

it('explains what a wildcard means on each Laravel version', function (string $version, string $meaning) {
    expect(EffectiveTrustProxies::describeWildcard($version))->toContain($meaning);
})->with([
    ['12.30.0', 'only the calling hop'],
    ['13.19.0', 'only the calling hop'],
    ['13.20.0', 'every hop'],
    ['13.34.0', 'every hop'],
]);
