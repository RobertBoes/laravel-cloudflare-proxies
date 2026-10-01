# laravel-cloudflare-proxies

Trusts Cloudflare's published ranges and the private hop of your reverse proxy, and
nothing else, so `request()->ip()` and `request()->isSecure()` are right behind
Cloudflare, and a made-up `X-Forwarded-For` entry never wins.

```bash
composer require robertboes/laravel-cloudflare-proxies
```

That is the whole setup. If `bootstrap/app.php` calls `trustProxies(at: ...)`, remove
it: the package owns that setting, and `php artisan cloudflare-proxies:check` fails
while the call is there.

## How it works

Cloudflare and your proxy (Caddy, nginx, Traefik) each append to
`X-Forwarded-For`. Laravel walks the header from the right, past the hops it trusts,
and stops at the first one it does not: the address Cloudflare appended for the
visitor. Anything the visitor sent sits to the left of that and is never reached.

```
X-Forwarded-For: 6.6.6.6, 9.9.9.9, 173.245.48.1     REMOTE_ADDR: 172.18.0.2
                 ^ made up ^ client  ^ Cloudflare edge          ^ your proxy
```

This holds as long as:

1. every trusted hop appends, and drops an incoming `X-Forwarded-For` from a peer it
   does not trust;
2. only your own Cloudflare zones can reach the origin. Trusting Cloudflare's ranges
   trusts every Cloudflare customer, so restrict the origin with zone-level
   [Authenticated Origin Pulls](https://developers.cloudflare.com/ssl/origin-configuration/authenticated-origin-pull/),
   not the shared certificate.

Nothing rewrites `REMOTE_ADDR` from `CF-Connecting-IP`: that collapses the chain and
hides the proxy hop, which is how `X-Forwarded-Proto` ends up ignored.

### Why not `TRUSTED_PROXIES=*`

On Laravel 13.20.0 and later, `'*'` trusts every hop, so the leftmost
`X-Forwarded-For` entry wins and any visitor can choose their IP with one header.
Before 13.20.0 it trusts only the calling hop, so behind a proxy every visitor
resolves to a Cloudflare edge address.

## Configuration

Publishing is optional; the defaults work as installed.

```bash
php artisan vendor:publish --tag=cloudflare-proxies-config
```

| Key | Default | |
| --- | --- | --- |
| `enabled` | `CLOUDFLARE_PROXIES_ENABLED`, `true` | Off leaves Laravel's own configuration in place. |
| `cloudflare` | `true` | Trust Cloudflare's ranges. |
| `private` | `true` | Trust `127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `::1/128` and `fc00::/7`: a proxy on a Docker network. |
| `proxies` | `CLOUDFLARE_PROXIES_EXTRA` | Further hops, comma separated, such as a load balancer's range. |
| `headers` | `X-Forwarded-For`, `X-Forwarded-Proto` | A `Request::HEADER_*` bitmask. |
| `refresh` | `CLOUDFLARE_PROXIES_REFRESH`, `false` | See *Keeping the ranges current*. |

`private` is deliberately narrower than Symfony's `PRIVATE_SUBNETS`, which also covers
carrier-grade NAT (Tailscale), documentation ranges, and the Teredo and 6to4 tunnel
ranges. Real clients live there, and trusting them lets a client spoof the walk.

`X-Forwarded-Host` and `X-Forwarded-Port` are not trusted: they carry a single value
that a visitor can set and Laravel reads the leftmost one. Turn on
`$middleware->trustHosts()` in `bootstrap/app.php` as well.

## Proving it in your tests

Add the trait to your feature test case:

```php
// tests/Pest.php
pest()->extend(Tests\TestCase::class)
    ->use(RobertBoes\CloudflareProxies\Testing\InteractsWithCloudflareProxies::class)
    ->in('Feature');
```

```php
it('resolves the client behind Cloudflare')->expectCloudflareProxiesToWork();
```

With PHPUnit, use the trait and call `$this->expectCloudflareProxiesToWork()`.

The helper sends requests through your app's real middleware stack and checks that:

- an untrusted remote address is used as is, and cannot claim https;
- a private hop with a single entry resolves to that entry;
- a chain with a spoofed leading entry resolves to the address Cloudflare appended,
  for IPv4 and IPv6;
- `X-Forwarded-Proto: https` through the chain makes the request secure;
- a spoofed `X-Forwarded-Host` and `X-Forwarded-Port` are ignored.

## Checking a deployment

```bash
php artisan cloudflare-proxies:check
```

Prints the trusted ranges, whether trusted hosts and the runtime refresh are on, and
what each sample request resolves to. It exits non-zero if the app overrides the
package (`trustProxies(at: ...)`, `'*'`, `trustedproxy.proxies`, its own trusted
headers), if `TrustProxies` is missing from the middleware stack, or if a sample
request resolves wrongly. Run it in CI or on deploy.

## Keeping the ranges current

The ranges ship with the package, in `resources/cloudflare-ips.json`. A daily job in
this repository fetches Cloudflare's lists, and a change becomes a patch release, so
Dependabot or Renovate is enough to stay current. No request ever fetches anything.

For an app that cannot wait for a release, turn on `refresh` and schedule the
command:

```php
// routes/console.php
Schedule::command('cloudflare-proxies:refresh')->daily();
```

The refresh validates every line, refuses empty, private, reserved or overly wide
ranges (wider than /12 for IPv4 or /28 for IPv6), and stores the result in the
default cache store. It can add ranges to the bundled snapshot, never remove them. A
missing or broken cache means the snapshot alone. Octane and queue workers pick up a
refresh when they restart.

## License

MIT.
