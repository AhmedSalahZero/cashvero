<?php

namespace Tests\Fixtures;

/**
 * The hand-worked answer sheets in tests/Fixtures/*.json.
 *
 * Every sheet's numbers were worked out by hand (its `_about` says how), not copied from the
 * code — so a test that compares the code against a sheet proves the code right, rather than
 * proving the code equals itself. Several sheets are read by the frontend tests too
 * (tests/js), so the screen and the server cannot quietly drift apart.
 *
 *   foreach (Fixtures::load('loan_split_cases')['cases'] as $case) { ... }
 */
final class Fixtures
{
    public static function load(string $name): array
    {
        $path = __DIR__.'/'.$name.'.json';

        if (! is_file($path)) {
            throw new \RuntimeException("No answer sheet called {$name} in tests/Fixtures.");
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        unset($data['_about']);

        return $data;
    }
}
