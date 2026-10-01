<?php

namespace RobertBoes\CloudflareProxies\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;
use RobertBoes\CloudflareProxies\Testing\SampleAddresses as Sample;

/**
 * Use on a Laravel feature test case. In Pest:
 *
 *     it('resolves the client behind Cloudflare')->expectCloudflareProxiesToWork();
 *
 * @mixin TestCase
 */
trait InteractsWithCloudflareProxies
{
    public function expectCloudflareProxiesToWork(): void
    {
        $scenarios = app(Scenarios::class);

        Assert::assertTrue($scenarios->applicable(), 'expectCloudflareProxiesToWork() needs cloudflare-proxies.enabled and cloudflare-proxies.cloudflare to be true.');

        $path = '/__cloudflare-proxies-probe';

        // An absolute http:// URL: a relative one is built from the previous request,
        // so after the https scenario every later request would be https too.
        $probe = 'http://'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost').$path;

        Route::get($path, fn (Request $request): array => [
            'ip' => $request->ip(),
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'url' => url('/'),
        ]);

        foreach ($scenarios->all() as $scenario) {
            $this->flushHeaders();

            $result = $this
                ->withServerVariables(['REMOTE_ADDR' => $scenario->remoteAddress])
                ->withHeaders($scenario->headers)
                ->get($probe)
                ->assertOk()
                ->json();

            $context = "Scenario: {$scenario->description}.\nRemote address: {$scenario->remoteAddress}\nHeaders: ".json_encode($scenario->headers, JSON_UNESCAPED_SLASHES)."\n"
                .'If bootstrap/app.php calls trustProxies(at: ...), remove it: the package owns that setting. `php artisan cloudflare-proxies:check` explains what is configured.';

            Assert::assertSame($scenario->expectedIp, $result['ip'], "The client IP is wrong.\n{$context}");
            Assert::assertSame($scenario->expectedSecure, $result['secure'], "The request scheme is wrong.\n{$context}");
            Assert::assertNotSame(Sample::SPOOFED_HOST, $result['host'], "A spoofed X-Forwarded-Host was trusted.\n{$context}");
            Assert::assertStringNotContainsString(':'.Sample::SPOOFED_PORT, $result['url'], "A spoofed X-Forwarded-Port was trusted.\n{$context}");
        }

        $this->flushHeaders();
        $this->withServerVariables([]);
    }
}
