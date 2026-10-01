<?php

namespace RobertBoes\CloudflareProxies\Commands;

use Illuminate\Console\Command;
use RobertBoes\CloudflareProxies\Ranges\RangeFetcher;
use RobertBoes\CloudflareProxies\Ranges\RefreshedRanges;
use RobertBoes\CloudflareProxies\Ranges\Snapshot;
use Throwable;

class RefreshCommand extends Command
{
    protected $signature = 'cloudflare-proxies:refresh';

    protected $description = "Fetch Cloudflare's current ranges and trust them alongside the bundled snapshot";

    public function handle(RangeFetcher $fetcher, RefreshedRanges $refreshed): int
    {
        if (! config('cloudflare-proxies.refresh')) {
            $this->components->error('Runtime refresh is off, so a refreshed list would never be used. Set CLOUDFLARE_PROXIES_REFRESH=true, or stop scheduling this command: the bundled snapshot is kept current by package releases.');

            return self::FAILURE;
        }

        try {
            $ranges = $fetcher->fetch();
        } catch (Throwable $exception) {
            $this->components->error("Cloudflare's ranges were not refreshed: {$exception->getMessage()} The last good list stays in use.");

            return self::FAILURE;
        }

        try {
            $refreshed->put($ranges);
        } catch (Throwable $exception) {
            $this->components->error("Cloudflare's ranges were fetched but could not be stored in the cache ({$exception->getMessage()}). Check that the default cache store works; until then the bundled snapshot is used.");

            return self::FAILURE;
        }

        $added = $ranges->missingFrom(Snapshot::load());

        $this->components->info(sprintf(
            'Stored %d IPv4 and %d IPv6 ranges. %s',
            count($ranges->ipv4),
            count($ranges->ipv6),
            $added === [] ? 'They match the bundled snapshot.' : 'New since the snapshot: '.implode(', ', $added).'.',
        ));

        if ($added !== []) {
            $this->components->warn('Long-running workers (Octane, queue workers) pick up new ranges when they restart.');
        }

        return self::SUCCESS;
    }
}
