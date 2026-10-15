<?php

namespace Tests\Feature\Translation;

use Tests\TestCase;

/**
 * Guards the two ways a string can silently escape translation:
 *
 *  1. A literal English label defined in <script> and rendered raw in the
 *     template (`{{ label }}` instead of `{{ $t(label) }}`). `npm run build`
 *     is happy, and the page just shows English under /ar.
 *  2. The inverse mistake — `$t()` wrapped around a *lookup key* rather than
 *     the text (`errorFor($t('x'))`). That one is worse than untranslated:
 *     the lookup misses and the message renders empty.
 */
class VueTranslationContractTest extends TestCase
{
    /** Functions whose first argument is a key, never display text. */
    private const KEY_TAKING_HELPERS = ['errorFor', 'fieldError'];

    private function vueFiles(): array
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js')));
        $files = [];
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'vue') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }

    public function test_translation_helpers_never_receive_a_translated_lookup_key(): void
    {
        $offenders = [];
        $helpers = implode('|', self::KEY_TAKING_HELPERS);

        foreach ($this->vueFiles() as $file) {
            foreach (file($file) as $i => $line) {
                if (preg_match('/\b(' . $helpers . ')\(\$t\(/', $line, $m)) {
                    $offenders[] = $this->rel($file) . ':' . ($i + 1) . '  ' . $m[1] . '($t(...))';
                }
            }
        }

        $this->assertSame([], $offenders, "\$t() must wrap displayed text, not a lookup key.\n"
            . "These call sites resolve the key to Arabic first, so the lookup misses\n"
            . "and the message renders empty:\n  " . implode("\n  ", $offenders));
    }

    public function test_hardcoded_option_labels_are_rendered_through_t(): void
    {
        // Label maps that live in <script> and feed a <select>. Rendering the
        // value raw leaves it English on /ar — the CashExpense search dropdown
        // regression this test was written for.
        $offenders = [];

        foreach ($this->vueFiles() as $file) {
            $src = file_get_contents($file);
            if (! preg_match('/<script[^>]*>(.*?)<\/script>/s', $src, $sm)) {
                continue;
            }
            $script = $sm[1];

            preg_match_all(
                '/<option\b[^>]*v-for="\(?\s*(\w+)\s*(?:,\s*(\w+)\s*)?\)?\s+in\s+([\w$][\w$.\[\]]*)"[^>]*>\s*\{\{\s*([\w.]+)\s*\}\}/',
                $src,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            );

            foreach ($matches as $m) {
                [$item, $rendered] = [$m[1][0], $m[4][0]];
                if (explode('.', $rendered)[0] !== $item && $rendered !== ($m[2][0] ?? null)) {
                    continue;
                }
                $root = preg_split('/[.\[]/', $m[3][0])[0];

                // Only local literal lists — props are localised server-side by __().
                if (! preg_match('/\b(?:const|let|var)\s+' . preg_quote($root, '/') . '\s*=\s*[\[{](.*?)[\]}];/s', $script, $lm)) {
                    continue;
                }
                // Does the list hold English display text (multi-word Title Case)?
                if (! preg_match("/['\"]([A-Z][A-Za-z]*(?: [A-Za-z&]+)+)['\"]/", $lm[1])) {
                    continue;
                }

                $line = substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
                $offenders[] = $this->rel($file) . ":{$line}  {{ {$rendered} }} from `{$root}`";
            }
        }

        $this->assertSame([], $offenders, "These <option> lists render hardcoded English labels raw.\n"
            . "Wrap them: {{ \$t(label) }}\n  " . implode("\n  ", $offenders));
    }

    public function test_every_literal_t_key_has_an_arabic_entry(): void
    {
        $ar = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $this->assertIsArray($ar);

        $missing = [];
        foreach ($this->vueFiles() as $file) {
            preg_match_all('/\$t\(\s*([\'"])((?:\\\\.|(?!\1)[^\\\\])*)\1\s*\)/', file_get_contents($file), $m);
            foreach ($m[2] as $i => $key) {
                // The source carries JS escapes (\' inside a single-quoted
                // literal); the runtime key does not.
                $key = stripcslashes($key);
                // snake_case / kebab-case keys are validation attribute names,
                // resolved by Laravel's own attribute files, not the UI dictionary.
                if (preg_match('/^[a-z][a-z0-9_.\-]*$/', $key)) {
                    continue;
                }
                if (! array_key_exists($key, $ar)) {
                    $missing[$key] = $this->rel($file);
                }
            }
        }

        $report = [];
        foreach ($missing as $key => $file) {
            $report[] = "'{$key}' in {$file}";
        }

        $this->assertSame([], $report, "These \$t() keys render English under /ar:\n  "
            . implode("\n  ", $report));
    }

    public function test_back_arrow_follows_reading_direction(): void
    {
        $src = file_get_contents(resource_path('js/Pages/Instructions/Show.vue'));

        $this->assertMatchesRegularExpression(
            '/locale === \'ar\'\s*\?\s*\'→\'\s*:\s*\'←\'/u',
            $src,
            'The back arrow must point right under RTL; a hardcoded ← points out of the page in Arabic.'
        );
        $this->assertStringNotContainsString("\$t('← Back')", $src,
            'Baking the arrow into the translation key makes it un-mirrorable.');
    }
}
