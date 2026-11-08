<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\StaticSecurityTestCase;

/**
 * SECURITY AUDIT — configuration and hardening.
 *
 * Checks the settings that decide how well the application defends itself
 * before any code runs: debug mode, cookies, sessions, password hashing,
 * CSRF protection, security headers.
 *
 * These read the configuration FILES (the defaults the code ships with), not
 * your machine's .env. That is deliberate: it proves what a fresh install
 * does, which is what a customer's auditor will ask about. What your live
 * server's .env says is a separate deployment check — see the checklist in
 * tests/Feature/Security/README.md.
 *
 * Needs no database and no booted application.
 */
#[Group('security')]
class ConfigurationSecurityTest extends StaticSecurityTestCase
{
    /** Security headers an auditor looks for, and the app-side words that prove each is set. */
    private const REQUIRED_HEADERS = [
        'X-Content-Type-Options' => ['x-content-type-options'],
        'X-Frame-Options (or CSP frame-ancestors)' => ['x-frame-options', 'frame-ancestors'],
        'Content-Security-Policy' => ['content-security-policy'],
        'Strict-Transport-Security (HSTS)' => ['strict-transport-security'],
        'Referrer-Policy' => ['referrer-policy'],
    ];

    /** Packages that exist to help developers and must never be installed on a production server. */
    private const DEVELOPER_ONLY_PACKAGES = [
        'barryvdh/laravel-debugbar',
        'spatie/laravel-ignition',
        'barryvdh/laravel-ide-helper',
        'beyondcode/laravel-query-detector',
        'laravel/sail',
        'nunomaduro/collision',
    ];

    public function test_debug_mode_is_off_unless_explicitly_switched_on(): void
    {
        $this->assertMatchesRegularExpression(
            '/[\'"]debug[\'"]\s*=>\s*env\(\s*[\'"]APP_DEBUG[\'"]\s*,\s*false\s*\)/',
            $this->read('config/app.php'),
            Finding::message(
                'SEC-CFG-DEBUG', 'HIGH',
                'Debug mode is on by default',
                ["config/app.php: 'debug' should default to false"],
                'In debug mode every error page prints file paths, SQL, environment values and sometimes passwords to whoever triggers the error.',
                "Use 'debug' => env('APP_DEBUG', false), and make sure the live server's .env says APP_DEBUG=false."
            )
        );
    }

    public function test_the_session_cookie_is_hidden_from_scripts_and_not_sent_cross_site(): void
    {
        $session = $this->read('config/session.php');

        $this->assertMatchesRegularExpression(
            '/[\'"]http_only[\'"]\s*=>\s*true/',
            $session,
            'config/session.php: http_only must be true, otherwise a script injected into a page can steal the session cookie.'
        );

        $this->assertMatchesRegularExpression(
            '/[\'"]same_site[\'"]\s*=>\s*[\'"](lax|strict)[\'"]/i',
            $session,
            'config/session.php: same_site must be "lax" or "strict" so other websites cannot make the browser send the session cookie along with a forged request.'
        );
    }

    public function test_sessions_expire_after_a_reasonable_idle_time(): void
    {
        $this->assertSame(
            1,
            preg_match('/[\'"]lifetime[\'"]\s*=>\s*env\(\s*[\'"]SESSION_LIFETIME[\'"]\s*,\s*(\d+)\s*\)/', $this->read('config/session.php'), $m),
            'Could not read the session lifetime default from config/session.php; update this test.'
        );

        $this->assertLessThanOrEqual(
            120,
            (int) $m[1],
            'A forgotten browser tab on a shared computer stays signed in to a treasury system for this many minutes. Keep it at 120 or less.'
        );
    }

    public function test_password_reset_links_expire_quickly(): void
    {
        $this->assertSame(
            1,
            preg_match('/[\'"]expire[\'"]\s*=>\s*(\d+)/', $this->read('config/auth.php'), $m),
            'Could not read the reset-link lifetime from config/auth.php; update this test.'
        );

        $this->assertLessThanOrEqual(60, (int) $m[1], 'Password-reset links should stop working within an hour.');
    }

    public function test_passwords_are_stored_with_a_strong_slow_hash(): void
    {
        $hashing = $this->read('config/hashing.php');

        $this->assertMatchesRegularExpression('/[\'"]driver[\'"]\s*=>\s*[\'"](bcrypt|argon|argon2id)[\'"]/', $hashing);

        $this->assertSame(
            1,
            preg_match('/[\'"]rounds[\'"]\s*=>\s*env\(\s*[\'"]BCRYPT_ROUNDS[\'"]\s*,\s*(\d+)\s*\)/', $hashing, $m),
            'Could not read the bcrypt cost from config/hashing.php; update this test.'
        );

        $this->assertGreaterThanOrEqual(10, (int) $m[1], 'bcrypt cost below 10 makes stolen password hashes quick to crack.');
    }

    public function test_csrf_protection_is_on_for_every_web_route_and_has_no_exemptions(): void
    {
        $kernel = $this->read('app/Http/Kernel.php');

        $this->assertStringContainsString(
            'VerifyCsrfToken::class',
            $kernel,
            'The web middleware group no longer includes VerifyCsrfToken: every form on the site could be submitted by another website on the visitor\'s behalf.'
        );

        $csrf = $this->read('app/Http/Middleware/VerifyCsrfToken.php');

        $this->assertSame(1, preg_match('/\$except\s*=\s*\[(.*?)\]\s*;/s', $csrf, $m), 'Could not read VerifyCsrfToken::$except; update this test.');

        // Strip comments, then there must be nothing left in the list.
        $entries = trim(preg_replace(['#//[^\n]*#', '#/\*.*?\*/#s'], '', $m[1]) ?? '');

        $this->assertSame(
            '',
            $entries,
            'VerifyCsrfToken::$except lists URLs that skip CSRF protection. Every entry is a form another website can submit as your user. Remove it, or protect the URL with a signed token instead.'
        );
    }

    public function test_cookies_are_encrypted(): void
    {
        $this->assertStringContainsString(
            'EncryptCookies::class',
            $this->read('app/Http/Kernel.php'),
            'The web middleware group no longer includes EncryptCookies.'
        );
    }

    /**
     * Guard for the future. TrustProxies exists in the project but is NOT registered in the
     * Kernel today, so it does nothing. If someone switches it on while it still says
     * `$proxies = '*'`, any visitor could forge the X-Forwarded-For header: their IP address
     * (used by the login lock-out) and the request scheme become whatever they claim.
     */
    public function test_proxy_trust_is_not_wide_open_if_it_is_switched_on(): void
    {
        $kernel = $this->read('app/Http/Kernel.php');

        if (! str_contains($kernel, 'TrustProxies')) {
            $this->addToAssertionCount(1);

            return;
        }

        $proxies = $this->read('app/Http/Middleware/TrustProxies.php');

        $this->assertDoesNotMatchRegularExpression(
            '/\$proxies\s*=\s*[\'"]\*[\'"]/',
            $proxies,
            Finding::message(
                'SEC-CFG-PROXY', 'MEDIUM',
                'TrustProxies is active and trusts every sender',
                ['app/Http/Middleware/TrustProxies.php: $proxies = \'*\''],
                'Anyone can send an X-Forwarded-For header; the app would believe it. That defeats the login lock-out (an attacker rotates the "IP" on every try) and corrupts the audit log.',
                'List the address(es) of your load balancer or reverse proxy instead of "*".'
            )
        );
    }

    public function test_developer_only_packages_are_not_installed_in_production(): void
    {
        $composer = json_decode($this->read('composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $installedEverywhere = array_keys($composer['require'] ?? []);
        $leaked = array_values(array_intersect(self::DEVELOPER_ONLY_PACKAGES, $installedEverywhere));

        $this->assertSame([], $leaked, Finding::message(
            'SEC-CFG-DEVPKG', 'HIGH',
            'Developer tools are listed as production requirements',
            $leaked,
            'Debug bars and error-solution pages expose queries, environment values and (in some versions) remote code execution when left reachable on a live server.',
            'Move them to "require-dev" and deploy with `composer install --no-dev`.'
        ));
    }

    public function test_secret_files_are_excluded_from_version_control(): void
    {
        $lines = array_map('trim', preg_split('/\r\n|\n|\r/', $this->read('.gitignore')) ?: []);

        $this->assertContains('.env', $lines, 'The .env file (database password, application key, Odoo/mail credentials) must be listed in .gitignore.');
    }

    /**
     * Security headers tell the browser to refuse clickjacking, content-type sniffing and
     * plain-HTTP downgrade. They can be set here (a middleware) or in the web server (nginx /
     * Apache / IIS). This project ships without its web-server config, so this test can only look
     * at the application. If YOUR SERVER sets them, set the environment variable
     * SECURITY_HEADERS_AT_PROXY=true when running the tests and confirm the live result with
     * https://securityheaders.com.
     */
    public function test_the_application_sets_the_standard_security_headers(): void
    {
        if (filter_var(getenv('SECURITY_HEADERS_AT_PROXY'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('SECURITY_HEADERS_AT_PROXY=true: headers are set by the web server. Verify the live site at https://securityheaders.com.');
        }

        $haystack = '';

        foreach (['app', 'bootstrap', 'config', 'routes'] as $dir) {
            foreach ($this->scan->files($dir, 'php') as $file) {
                $haystack .= "\n".strtolower((string) file_get_contents($file));
            }
        }

        $missing = [];

        foreach (self::REQUIRED_HEADERS as $label => $needles) {
            $found = false;

            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    $found = true;

                    break;
                }
            }

            if (! $found) {
                $missing[] = $label;
            }
        }

        $this->assertSame([], $missing, Finding::message(
            'SEC-16', 'MEDIUM',
            'The application does not set standard browser security headers',
            $missing,
            'Without them a hostile site can load CashVero inside an invisible frame and trick a user into clicking (clickjacking), browsers may guess file types in a dangerous way, and nothing forces HTTPS. Every external penetration test reports this.',
            'Add one small middleware to the web group that sends these headers (Laravel makes this ~15 lines), or set them in the web server and run the tests with SECURITY_HEADERS_AT_PROXY=true. Start the Content-Security-Policy in report-only mode, because the Vue/Inertia pages need allowances.'
        ));
    }
}
