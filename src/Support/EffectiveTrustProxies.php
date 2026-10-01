<?php

namespace RobertBoes\CloudflareProxies\Support;

use Illuminate\Http\Middleware\TrustProxies;
use ReflectionProperty;

/**
 * What TrustProxies will actually use, whoever set it last.
 */
final class EffectiveTrustProxies
{
    /**
     * @return array<int, string>|string|null
     */
    public static function proxies(): array|string|null
    {
        return (new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies'))->getValue();
    }

    public static function headers(): ?int
    {
        return (new ReflectionProperty(TrustProxies::class, 'alwaysTrustHeaders'))->getValue();
    }

    /**
     * Laravel 13.20.0 changed '*' from "trust the calling hop" to "trust every hop".
     */
    public static function describeWildcard(string $laravelVersion): string
    {
        return version_compare($laravelVersion, '13.20.0', '>=')
            ? "On Laravel {$laravelVersion}, '*' trusts every hop, so the leftmost X-Forwarded-For entry wins and any visitor can choose their IP."
            : "On Laravel {$laravelVersion}, '*' trusts only the calling hop, so behind Cloudflare and a proxy every visitor resolves to a Cloudflare edge address.";
    }
}
