<?php

namespace Tests\Feature\Statements;

use Tests\TestCase;

/**
 * The printable record views.
 *
 * Everything asserted here is about the PAPER, not the screen — the class
 * of thing nobody notices until a printout comes out wrong: a trailing
 * blank page, a second page of unlabelled numbers, margins that differ
 * per browser.
 */
class PrintViewTest extends TestCase
{
    /** @return string[] */
    private function printViews(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js/Pages')));

        foreach ($it as $file) {
            if ($file->isFile() && $file->getFilename() === 'Print.vue') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function rel(string $path): string
    {
        return str_replace(resource_path('js/Pages').'/', '', $path);
    }

    public function test_there_are_print_views_to_check(): void
    {
        $this->assertNotEmpty($this->printViews());
    }

    /**
     * `min-height: 100vh` is right on screen and wrong on paper: it holds
     * the sheet to a full viewport height and prints a trailing blank page.
     */
    public function test_the_full_height_screen_rule_is_undone_for_print(): void
    {
        $offenders = [];

        foreach ($this->printViews() as $file) {
            $source = file_get_contents($file);

            if (! str_contains($source, 'min-height: 100vh')) {
                continue;
            }

            $printBlock = strstr($source, '@media print');

            if (! $printBlock || ! preg_match('/\.print-page\s*\{[^}]*min-height:\s*0/', $printBlock)) {
                $offenders[] = $this->rel($file);
            }
        }

        $this->assertSame([], $offenders,
            "These print views keep a full-viewport height when printing, which adds a blank page:\n  "
            .implode("\n  ", $offenders));
    }

    /**
     * A settlement list can run past one page. Without a repeating header
     * the second page is a wall of unlabelled numbers.
     */
    public function test_table_headings_repeat_across_pages(): void
    {
        $offenders = [];

        foreach ($this->printViews() as $file) {
            $source = file_get_contents($file);

            if (! str_contains($source, '<thead>')) {
                continue;
            }

            $printBlock = strstr($source, '@media print');

            if (! $printBlock || ! str_contains($printBlock, 'table-header-group')) {
                $offenders[] = $this->rel($file);
            }
        }

        $this->assertSame([], $offenders,
            "These print views have a table whose headings would not repeat on a second page:\n  "
            .implode("\n  ", $offenders));
    }

    public function test_the_page_size_and_margin_are_stated(): void
    {
        $offenders = [];

        foreach ($this->printViews() as $file) {
            if (! preg_match('/@page\s*\{[^}]*margin/', file_get_contents($file))) {
                $offenders[] = $this->rel($file);
            }
        }

        $this->assertSame([], $offenders,
            "Without an @page margin each browser picks its own, so the same record prints "
            ."differently on different machines:\n  ".implode("\n  ", $offenders));
    }

    /** The button must never appear on the paper. */
    public function test_the_print_button_is_hidden_when_printing(): void
    {
        $offenders = [];

        foreach ($this->printViews() as $file) {
            $source = file_get_contents($file);
            $printBlock = strstr($source, '@media print');

            if (! $printBlock || ! preg_match('/\.no-print\s*\{\s*display:\s*none/', $printBlock)) {
                $offenders[] = $this->rel($file);
            }
        }

        $this->assertSame([], $offenders, "The on-screen controls would print:\n  ".implode("\n  ", $offenders));
    }

    /**
     * Amounts must read left-to-right even on an Arabic page, or the
     * digits of a figure reorder and the number reads wrong.
     */
    public function test_amounts_stay_left_to_right(): void
    {
        $offenders = [];

        foreach ($this->printViews() as $file) {
            if (! preg_match('/\.num\s*\{[^}]*direction:\s*ltr/', file_get_contents($file))) {
                $offenders[] = $this->rel($file);
            }
        }

        $this->assertSame([], $offenders,
            "Numeric cells must be forced LTR so figures do not reorder under RTL:\n  "
            .implode("\n  ", $offenders));
    }

    /** A record handed over on paper needs somewhere to sign. */
    public function test_every_printout_has_signature_lines(): void
    {
        $offenders = [];

        foreach ($this->printViews() as $file) {
            $source = file_get_contents($file);

            if (! str_contains($source, "\$t('Prepared By')")) {
                $offenders[] = $this->rel($file);
            }
        }

        $this->assertSame([], $offenders,
            "These printouts have nowhere to sign:\n  ".implode("\n  ", $offenders));
    }

    /** Every visible string on the paper has to be translatable. */
    public function test_no_untranslated_text_on_the_printout(): void
    {
        $arabic = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $missing = [];

        foreach ($this->printViews() as $file) {
            preg_match_all('/\$t\(\s*([\'"])(.+?)\1\s*\)/', file_get_contents($file), $m);

            foreach ($m[2] as $key) {
                if (! array_key_exists(stripcslashes($key), $arabic)) {
                    $missing[] = $this->rel($file).': '.$key;
                }
            }
        }

        $this->assertSame([], $missing,
            "Untranslated text would print in English on an Arabic document:\n  ".implode("\n  ", $missing));
    }
}
