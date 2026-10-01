<?php

namespace RobertBoes\CloudflareProxies\Tests;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;
use RobertBoes\CloudflareProxies\CloudflareProxiesServiceProvider;
use RobertBoes\CloudflareProxies\Testing\InteractsWithCloudflareProxies;

abstract class TestCase extends Orchestra
{
    use InteractsWithCloudflareProxies;

    protected function setUp(): void
    {
        TrustProxies::flushState();

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [CloudflareProxiesServiceProvider::class];
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/client', fn (Request $request): array => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
        ]);
    }

    /**
     * Changes the package config and runs its boot step again, as a fresh boot would.
     *
     * @param  array<string, mixed>  $config
     */
    protected function configureProxies(array $config): static
    {
        foreach ($config as $key => $value) {
            config()->set("cloudflare-proxies.{$key}", $value);
        }

        TrustProxies::flushState();
        (new CloudflareProxiesServiceProvider(app()))->packageBooted();

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{ip: string, secure: bool, host: string, port: int}
     */
    protected function client(string $remoteAddress, array $headers = []): array
    {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders($headers)
            ->get('/client')
            ->assertOk()
            ->json();
    }
}
