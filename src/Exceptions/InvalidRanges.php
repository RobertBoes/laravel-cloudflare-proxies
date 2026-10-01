<?php

namespace RobertBoes\CloudflareProxies\Exceptions;

use RobertBoes\CloudflareProxies\Ranges\IpFamily;
use RuntimeException;

class InvalidRanges extends RuntimeException
{
    public static function empty(IpFamily $family): self
    {
        return new self("The {$family->value} list is empty.");
    }

    public static function invalidEntry(IpFamily $family, string $entry, string $reason): self
    {
        $shown = mb_strimwidth($entry, 0, 60, '…');

        return new self("The {$family->value} list contains [{$shown}], which {$reason}.");
    }

    public static function malformed(string $reason): self
    {
        return new self("The stored ranges are malformed: {$reason}.");
    }
}
