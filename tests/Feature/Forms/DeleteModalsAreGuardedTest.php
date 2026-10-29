<?php

namespace Tests\Feature\Forms;

use Tests\TestCase;

/**
 * Double-clicking "Confirm Delete" sent two DELETE requests: the first
 * removed the record, the second hit a row that no longer existed and
 * Inertia showed the raw 404 page in a dialog. The modal only closed in
 * `onFinish`, so the button stayed clickable until the response came back.
 *
 * Every delete modal's confirm button must therefore be `:disabled` while
 * its request is in flight. This scans all pages so a new modal cannot be
 * added without the guard.
 */
class DeleteModalsAreGuardedTest extends TestCase
{
    /** Handlers the confirm buttons are wired to: destroyRow, destroyRenewal, performDelete... */
    private const CONFIRM_HANDLER = '(?:destroy\w*|performDelete)';

    /** @return array<int, array{file: string, handler: string, tag: string}> */
    private function confirmButtons(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js')));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $body = file_get_contents($file->getPathname());
            // /s: BankAccounts/Index.vue writes its confirm button over several lines.
            preg_match_all('/<button\b[^>]*@click="('.self::CONFIRM_HANDLER.')"[^>]*>/s', $body, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $found[] = [
                    'file' => str_replace(resource_path('js').'/', '', $file->getPathname()),
                    'handler' => $match[1],
                    'tag' => $match[0],
                ];
            }
        }

        return $found;
    }

    public function test_the_scan_actually_finds_the_delete_modals(): void
    {
        $this->assertGreaterThan(
            55,
            count($this->confirmButtons()),
            'A scan that finds nothing would make the assertion below pass vacuously.'
        );
    }

    public function test_every_delete_confirm_button_is_disabled_while_the_request_runs(): void
    {
        $unguarded = [];
        foreach ($this->confirmButtons() as $button) {
            if (! str_contains($button['tag'], ':disabled=')) {
                $unguarded[] = "{$button['file']} -> {$button['handler']}";
            }
        }

        $this->assertSame(
            [],
            $unguarded,
            'A second click on these sends another DELETE for a record that is already gone (404).'
        );
    }
}
