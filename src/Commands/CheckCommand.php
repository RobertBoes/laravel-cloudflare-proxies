<?php

namespace RobertBoes\CloudflareProxies\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use RobertBoes\CloudflareProxies\ProxyList;
use RobertBoes\CloudflareProxies\Ranges\CloudflareRanges;
use RobertBoes\CloudflareProxies\Ranges\RefreshedRanges;
use RobertBoes\CloudflareProxies\Ranges\Snapshot;
use RobertBoes\CloudflareProxies\Support\EffectiveTrustProxies;
use RobertBoes\CloudflareProxies\Testing\SampleAddresses;
use RobertBoes\CloudflareProxies\Testing\Scenario;
use RobertBoes\CloudflareProxies\Testing\Scenarios;

class CheckCommand extends Command
{
    protected $signature = 'cloudflare-proxies:check';

    protected $description = 'Show which proxies are trusted, and fail if the app overrides them';

    private const array HEADER_NAMES = [
        Request::HEADER_FORWARDED => 'Forwarded',
        Request::HEADER_X_FORWARDED_FOR => 'X-Forwarded-For',
        Request::HEADER_X_FORWARDED_HOST => 'X-Forwarded-Host',
        Request::HEADER_X_FORWARDED_PROTO => 'X-Forwarded-Proto',
        Request::HEADER_X_FORWARDED_PORT => 'X-Forwarded-Port',
        Request::HEADER_X_FORWARDED_PREFIX => 'X-Forwarded-Prefix',
    ];

    /** @var list<string> */
    private array $problems = [];

    public function handle(ProxyList $proxies, RefreshedRanges $refreshed, Scenarios $scenarios): int
    {
        if (! config('cloudflare-proxies.enabled')) {
            $this->components->warn("The package is disabled (cloudflare-proxies.enabled), so Laravel's own trusted proxy configuration applies.");

            return self::SUCCESS;
        }

        // Resolving the HTTP kernel runs the app's withMiddleware() callback, so any
        // trustProxies(at: ...) in bootstrap/app.php takes effect before we look.
        $globalMiddleware = $this->laravel->make(Kernel::class)->getGlobalMiddleware();

        $this->describeConfiguration($proxies, $refreshed, $globalMiddleware);
        $this->checkEffectiveConfiguration($proxies, $globalMiddleware);

        if ($scenarios->applicable()) {
            $this->checkScenarios($scenarios, $globalMiddleware);
        }

        if ($this->problems === []) {
            $this->components->info('Trusted proxies are configured by this package and resolve as expected.');

            return self::SUCCESS;
        }

        foreach ($this->problems as $problem) {
            $this->components->error($problem);
        }

        return self::FAILURE;
    }

    /**
     * @param  array<int, string>  $globalMiddleware
     */
    private function describeConfiguration(ProxyList $proxies, RefreshedRanges $refreshed, array $globalMiddleware): void
    {
        $snapshot = Snapshot::load();
        $cloudflare = $proxies->cloudflare();

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Trusted proxies</>');

        if (config('cloudflare-proxies.cloudflare')) {
            $this->components->twoColumnDetail('Cloudflare IPv4', count($cloudflare->ipv4).' ranges');
            $this->components->twoColumnDetail('Cloudflare IPv6', count($cloudflare->ipv6).' ranges');
            $this->components->twoColumnDetail('Snapshot', "fetched {$snapshot->fetchedAt}");
        } else {
            $this->components->twoColumnDetail('Cloudflare', 'off');
        }

        $this->components->twoColumnDetail('Private networks', config('cloudflare-proxies.private') ? implode(', ', ProxyList::PRIVATE_NETWORKS) : 'off');
        $this->components->twoColumnDetail('Extra proxies', implode(', ', $proxies->extra()) ?: 'none');
        $this->components->twoColumnDetail('Headers', $this->headerNames($proxies->headers()));
        $this->components->twoColumnDetail('Runtime refresh', $this->describeRefresh($refreshed, $snapshot));

        $this->components->twoColumnDetail('Trusted hosts', $this->hasMiddleware($globalMiddleware, TrustHosts::class)
            ? 'on'
            : '<fg=yellow>off: consider $middleware->trustHosts() in bootstrap/app.php</>');

        $this->newLine();
    }

    private function describeRefresh(RefreshedRanges $refreshed, CloudflareRanges $snapshot): string
    {
        if (! config('cloudflare-proxies.refresh')) {
            return 'off';
        }

        $cached = $refreshed->get();

        if ($cached === null) {
            return 'on, no cached refresh yet: the snapshot alone is trusted';
        }

        return sprintf('on, refreshed %s, adding %d ranges to the snapshot', $cached->fetchedAt, count($cached->missingFrom($snapshot)));
    }

    /**
     * @param  array<int, string>  $globalMiddleware
     */
    private function checkEffectiveConfiguration(ProxyList $proxies, array $globalMiddleware): void
    {
        $effective = EffectiveTrustProxies::proxies();

        if ($effective === '*' || $effective === '**') {
            $this->problems[] = "Trusted proxies are set to '{$effective}'. ".EffectiveTrustProxies::describeWildcard($this->laravel->version())
                .' Remove the trustProxies(at: ...) call (or TRUSTED_PROXIES) from bootstrap/app.php: this package sets the right list.';
        } elseif ($this->normalise($effective) !== $this->normalise($proxies->all())) {
            $this->problems[] = 'The trusted proxies were changed after this package set them, most likely by trustProxies(at: ...) in bootstrap/app.php. Remove that call: this package owns the setting, and two sources of truth resolve differently in requests and in tests.';
        }

        if (EffectiveTrustProxies::headers() !== $proxies->headers()) {
            $this->problems[] = sprintf(
                'The trusted headers were changed to [%s] after this package set them, most likely by trustProxies(headers: ...) in bootstrap/app.php. Set cloudflare-proxies.headers instead.',
                $this->headerNames((int) EffectiveTrustProxies::headers()),
            );
        }

        if (config('trustedproxy.proxies') !== null) {
            $this->problems[] = 'config/trustedproxy.php sets proxies, which Laravel uses whenever the trusted list is empty. Remove it: this package owns the setting.';
        }

        if (! $this->hasMiddleware($globalMiddleware, TrustProxies::class)) {
            $this->problems[] = 'TrustProxies is not in the global middleware stack, so no proxy is trusted at all. Restore it in bootstrap/app.php.';
        }
    }

    /**
     * @param  array<int, string>  $globalMiddleware
     */
    private function checkScenarios(Scenarios $scenarios, array $globalMiddleware): void
    {
        $middleware = collect($globalMiddleware)->first(fn (string $class): bool => is_a($class, TrustProxies::class, true), TrustProxies::class);
        $rows = [];

        foreach ($scenarios->all() as $scenario) {
            $request = $this->resolve($scenario, $this->laravel->make($middleware));
            $resolvedIp = (string) $request->ip();
            $passes = $resolvedIp === $scenario->expectedIp
                && $request->isSecure() === $scenario->expectedSecure
                && $request->getHost() !== SampleAddresses::SPOOFED_HOST;

            $rows[] = [
                ucfirst($scenario->description),
                $resolvedIp.($request->isSecure() ? ' (https)' : ''),
                $passes ? '<fg=green>ok</>' : "<fg=red>expected {$scenario->expectedIp}</>",
            ];

            if (! $passes) {
                $this->problems[] = "Sample request failed: {$scenario->description}.";
            }
        }

        $this->table(['Sample request', 'Resolves to', ''], $rows);
    }

    private function resolve(Scenario $scenario, TrustProxies $middleware): Request
    {
        $server = ['REMOTE_ADDR' => $scenario->remoteAddress];

        foreach ($scenario->headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = Request::create('http://localhost/', server: $server);
        $middleware->handle($request, fn () => null);

        return $request;
    }

    /**
     * @param  array<int, string>  $middleware
     * @param  class-string  $class
     */
    private function hasMiddleware(array $middleware, string $class): bool
    {
        return collect($middleware)->contains(fn (string $entry): bool => is_a($entry, $class, true));
    }

    private function headerNames(int $headers): string
    {
        $names = array_filter(self::HEADER_NAMES, fn (int $flag): bool => ($headers & $flag) === $flag, ARRAY_FILTER_USE_KEY);

        return implode(', ', $names) ?: 'none';
    }

    /**
     * @param  array<int, string>|string|null  $proxies
     * @return list<string>
     */
    private function normalise(array|string|null $proxies): array
    {
        $proxies = is_string($proxies) ? explode(',', $proxies) : ($proxies ?? []);
        $proxies = array_map(trim(...), $proxies);
        sort($proxies);

        return $proxies;
    }
}
