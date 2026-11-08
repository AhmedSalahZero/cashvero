<?php

namespace Tests\Feature\Security\Support;

/**
 * Formats a failed security check the way a penetration-test report would.
 *
 * When a security test fails, the failure IS the finding. Instead of a bare
 * "Failed asserting that an array is empty", the person reading the console
 * gets: an id they can quote (SEC-08), a severity, exactly where it is, why
 * it matters in plain language, and what to do about it.
 *
 * Severity follows the usual scale:
 *   CRITICAL  data can be taken or destroyed by anyone, no special access
 *   HIGH      a logged-in user can reach data or powers that are not theirs
 *   MEDIUM    weakens a defence, or needs another weakness to be exploited
 *   LOW       hygiene / hardening; unlikely to be exploited alone
 */
final class Finding
{
    /**
     * @param  list<string>  $evidence  one line per place the problem was found
     */
    public static function message(
        string $id,
        string $severity,
        string $title,
        array $evidence,
        string $why,
        string $fix,
    ): string {
        $lines = ["[{$id} · {$severity}] {$title}", ''];

        foreach (array_slice($evidence, 0, 25) as $item) {
            $lines[] = '   • '.$item;
        }

        if (count($evidence) > 25) {
            $lines[] = '   … and '.(count($evidence) - 25).' more';
        }

        $lines[] = '';
        $lines[] = 'Why it matters: '.$why;
        $lines[] = 'How to fix:     '.$fix;

        return implode("\n", $lines);
    }
}
