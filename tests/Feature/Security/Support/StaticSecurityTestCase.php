<?php

namespace Tests\Feature\Security\Support;

use PHPUnit\Framework\TestCase;

/**
 * Base class for the security checks that only READ the source code.
 *
 * These tests deliberately do not extend Laravel's TestCase: they need no
 * database, no web server and no booted application. That makes them fast
 * (a fraction of a second) and means they can never be skipped because a
 * database was unreachable — a silent skip is the enemy of a security check.
 */
abstract class StaticSecurityTestCase extends TestCase
{
    protected string $root;

    protected SourceScanner $scan;

    protected function setUp(): void
    {
        parent::setUp();

        // tests/Feature/Security/Support/<this file>  ->  project root is four levels up.
        $this->root = dirname(__DIR__, 4);
        $this->scan = new SourceScanner($this->root);
    }

    /** Contents of a project file, or fail loudly if it has moved. */
    protected function read(string $relativePath): string
    {
        $path = $this->root.'/'.$relativePath;

        $this->assertFileExists(
            $path,
            "Security test expected {$relativePath} to exist. If the file was moved or renamed, update this test so the check keeps protecting it."
        );

        return (string) file_get_contents($path);
    }
}
