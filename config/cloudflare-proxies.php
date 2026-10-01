<?php

use Illuminate\Http\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Turns the package off entirely, for an environment that is not behind
    | Cloudflare. Laravel's own trusted proxy configuration then applies.
    |
    */

    'enabled' => env('CLOUDFLARE_PROXIES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Trusted hops
    |--------------------------------------------------------------------------
    |
    | `cloudflare` trusts Cloudflare's published ranges. `private` trusts the
    | private networks a reverse proxy sits on (127.0.0.0/8, 10.0.0.0/8,
    | 172.16.0.0/12, 192.168.0.0/16, ::1/128 and fc00::/7). `proxies` adds
    | further hops, such as a load balancer's range, comma separated.
    |
    */

    'cloudflare' => true,

    'private' => true,

    'proxies' => env('CLOUDFLARE_PROXIES_EXTRA'),

    /*
    |--------------------------------------------------------------------------
    | Trusted headers
    |--------------------------------------------------------------------------
    |
    | A Request::HEADER_* bitmask. Only X-Forwarded-For is layered hop by hop;
    | Host, Port and Prefix carry a single value a visitor can set, so they
    | are not trusted unless every hop in front of the app overwrites them.
    |
    */

    'headers' => Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,

    /*
    |--------------------------------------------------------------------------
    | Runtime refresh
    |--------------------------------------------------------------------------
    |
    | When on, `php artisan cloudflare-proxies:refresh` stores Cloudflare's
    | current ranges in the cache and they are trusted alongside the bundled
    | snapshot. A request never fetches; schedule the command yourself.
    |
    */

    'refresh' => env('CLOUDFLARE_PROXIES_REFRESH', false),

];
