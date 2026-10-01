<?php

namespace RobertBoes\CloudflareProxies;

use Illuminate\Http\Middleware\TrustProxies;
use RobertBoes\CloudflareProxies\Commands\CheckCommand;
use RobertBoes\CloudflareProxies\Commands\RefreshCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CloudflareProxiesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-cloudflare-proxies')
            ->hasConfigFile('cloudflare-proxies')
            ->hasCommands([CheckCommand::class, RefreshCommand::class]);
    }

    public function packageBooted(): void
    {
        if (! config('cloudflare-proxies.enabled')) {
            return;
        }

        $proxies = $this->app->make(ProxyList::class);

        TrustProxies::at($proxies->all());
        TrustProxies::withHeaders($proxies->headers());
    }
}
