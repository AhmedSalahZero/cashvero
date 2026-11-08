<?php

namespace App\Support\Odoo;

/**
 * Decides whether a company's Odoo address is safe for the SERVER to connect to.
 *
 * CashVero's server calls this address with the user's Odoo login. If anyone who can edit a company could point it at
 * the server itself (127.0.0.1, localhost), the cloud provider's secret-keys page (169.254.169.254) or another machine
 * on the private network, the server could be made to knock on those doors (server-side request forgery).
 *
 * Two switches exist for installations whose Odoo really lives on a private network or speaks plain http:
 *   ODOO_ALLOW_PRIVATE_HOSTS=true   ODOO_ALLOW_HTTP=true      (both default to false, and http is always allowed in a "local" app)
 */
final class OdooUrlGuard
{
    /** @return string|null  why the address is refused, or null when it is acceptable */
    public static function violation(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return 'The Odoo address must be a full web address such as https://mycompany.odoo.com.';
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['https', 'http'], true)) {
            return 'The Odoo address must start with https://.';
        }

        if ($scheme === 'http' && ! self::allowed('ODOO_ALLOW_HTTP') && ! app()->environment('local')) {
            return 'The Odoo address must use https:// (the Odoo password would otherwise travel unencrypted).';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'The Odoo address must not contain a user name or password.';
        }

        if (self::allowed('ODOO_ALLOW_PRIVATE_HOSTS')) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return 'The Odoo address must not point at the server itself or an internal network name.';
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        foreach ($addresses as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return 'The Odoo address must not point at the server itself or a private network.';
            }
        }

        return null;
    }

    private static function allowed(string $envName): bool
    {
        return filter_var(env($envName, false), FILTER_VALIDATE_BOOLEAN);
    }
}
