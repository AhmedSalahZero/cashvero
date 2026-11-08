<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * SEC-40 — Are any of the installed software packages known to be vulnerable?
 * ==================================================================
 * CashVero is built on hundreds of open-source packages (Laravel and its helpers). Security researchers
 * publish a warning ("advisory") whenever one of them has a hole. Composer keeps that list and checks your
 * exact installed versions against it: `composer audit`. This test runs that check and fails when any
 * package that is used in production has a published advisory.
 *
 * It needs an internet connection (the advisory list is downloaded). If Composer cannot be run or
 * cannot reach the list, the test says so and stops, instead of pretending everything is fine.
 *
 * What to do when it fails: run `composer update <package>` (or `composer audit` to read the details),
 * then run the tests again.
 */
#[Group('security')]
class DependencyAuditTest extends TestCase
{
    public function test_no_production_package_has_a_known_security_advisory(): void
    {
        if (! is_file(base_path('composer.lock'))) {
            $this->markTestSkipped('There is no composer.lock file, so the installed versions cannot be checked.');
        }

        $process = Process::fromShellCommandline('composer audit --locked --no-dev --no-interaction --format=json', base_path());
        $process->setTimeout(180);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Composer could not be started: '.$e->getMessage());
        }

        $output = $process->getOutput();
        // PHP start-up warnings (for example an Imagick version notice) can be printed before the JSON.
        $start = strpos($output, '{');
        $data = $start === false ? null : json_decode(substr($output, $start), true);

        if (! is_array($data) || ! array_key_exists('advisories', $data)) {
            $this->markTestSkipped('Composer gave no readable answer (is it installed, and is the internet reachable?): '.substr(trim($process->getErrorOutput().$process->getOutput()), 0, 300));
        }

        $evidence = [];

        foreach ($data['advisories'] as $package => $advisories) {
            foreach ($advisories as $advisory) {
                $evidence[] = sprintf(
                    '%s — %s [%s] affects %s %s',
                    $package,
                    $advisory['title'] ?? 'advisory',
                    $advisory['cve'] ?? 'no CVE',
                    $advisory['affectedVersions'] ?? '?',
                    $advisory['link'] ?? ''
                );
            }
        }

        $this->assertSame([], $evidence, Finding::message(
            'SEC-40', 'HIGH',
            'A package used in production has a published security advisory',
            $evidence,
            'Attackers read the same public advisories and scan websites for the vulnerable versions. A known hole in a framework package is often the easiest way into an application.',
            'Run "composer audit" to read the details, update the listed packages ("composer update <package>"), run the tests, and deploy. If an advisory does not apply to how CashVero uses the package, record that decision in composer.json under config.audit.ignore.'
        ));
    }
}
