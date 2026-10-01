<?php

namespace RobertBoes\CloudflareProxies\Testing;

/**
 * Public addresses outside every range the package trusts. A package test
 * pins that, so a change to the trusted set cannot make a scenario pass for
 * the wrong reason.
 */
final class SampleAddresses
{
    public const string CLIENT_V4 = '9.9.9.9';

    public const string CLIENT_V6 = '2620:fe::9';

    public const string SPOOFED = '6.6.6.6';

    public const string UNTRUSTED_REMOTE = '8.8.8.8';

    public const string PRIVATE_HOP = '172.18.0.2';

    public const string SPOOFED_HOST = 'spoofed.example';

    public const string SPOOFED_PORT = '8443';

    /**
     * @return list<string>
     */
    public static function outsideTheTrustedSet(): array
    {
        return [self::CLIENT_V4, self::CLIENT_V6, self::SPOOFED, self::UNTRUSTED_REMOTE];
    }

    /**
     * The network address with its lowest bit set: 173.245.48.0/20 gives 173.245.48.1.
     */
    public static function firstHostIn(string $cidr): string
    {
        [$address, $prefix] = explode('/', $cidr);
        $bytes = (string) inet_pton($address);

        if ((int) $prefix < strlen($bytes) * 8) {
            $bytes[-1] = chr(ord($bytes[-1]) | 1);
        }

        return (string) inet_ntop($bytes);
    }
}
