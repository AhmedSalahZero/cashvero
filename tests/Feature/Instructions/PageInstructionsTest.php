<?php

namespace Tests\Feature\Instructions;

use App\Support\Instructions\PageInstructions;
use Tests\TestCase;

/**
 * The written guides behind each screen's "Instructions" button.
 *
 * A guide that is out of date is worse than no guide, so what is
 * pinned here is not the prose but the things that silently rot:
 * every guide is reachable, every string it renders has an Arabic
 * translation, and every validation message it quotes is still a
 * message the application actually produces.
 *
 * @see \App\Support\Instructions\PageInstructions
 */
class PageInstructionsTest extends TestCase
{
    /** Every string a guide renders, flattened. */
    private function stringsOf(string $key): array
    {
        $page = PageInstructions::get($key);
        $strings = [$page['title'], $page['summary']];

        foreach ($page['sections'] as $section) {
            $strings[] = $section['heading'];
            foreach ($section['body'] ?? [] as $paragraph) {
                $strings[] = $paragraph;
            }
            foreach ($section['fields'] ?? [] as $field) {
                $strings[] = $field['label'];
                $strings[] = $field['text'];
                if (isset($field['example'])) {
                    $strings[] = $field['example'];
                }
            }
            if (isset($section['example'])) {
                $strings[] = $section['example'];
            }
            foreach ($section['notes'] ?? [] as $note) {
                $strings[] = $note;
            }
        }

        return $strings;
    }

    public function test_every_declared_guide_has_content(): void
    {
        $this->assertNotEmpty(PageInstructions::keys());

        foreach (PageInstructions::keys() as $key) {
            $page = PageInstructions::get($key);

            $this->assertIsArray($page, "{$key} has no content.");
            $this->assertNotEmpty($page['title'], "{$key} has no title.");
            $this->assertNotEmpty($page['summary'], "{$key} has no summary.");
            $this->assertNotEmpty($page['sections'], "{$key} has no sections.");
        }
    }

    /** An unknown key must 404 rather than render an empty page. */
    public function test_an_unknown_guide_is_not_recognised(): void
    {
        $this->assertFalse(PageInstructions::has('not-a-real-page'));
        $this->assertNull(PageInstructions::get('not-a-real-page'));
    }

    /**
     * The guides are rendered through $t() like the rest of the app, so
     * an untranslated string shows up as English inside an otherwise
     * Arabic page. This is what stops that happening quietly when
     * someone edits the wording.
     */
    public function test_every_guide_string_is_translated_into_arabic(): void
    {
        $arabic = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $untranslated = [];

        foreach (PageInstructions::keys() as $key) {
            foreach ($this->stringsOf($key) as $string) {
                if (! array_key_exists($string, $arabic)) {
                    $untranslated[] = $key.': '.mb_substr($string, 0, 60);
                }
            }
        }

        $this->assertSame([], $untranslated, sprintf(
            "%d guide string(s) have no Arabic in resources/lang/ar.json:\n  %s\n",
            count($untranslated), implode("\n  ", $untranslated)
        ));
    }

    /**
     * The guides quote validation messages verbatim so a reader can
     * match the red text on screen to the explanation. If a message is
     * reworded in the code and not here, that promise is broken —
     * this checks each quoted message still exists in the app.
     */
    public function test_every_quoted_validation_message_still_exists_in_the_app(): void
    {
        $sources = collect([
            app_path('Http/Requests/StoreMoneyReceivedRequest.php'),
            app_path('Http/Requests/DeleteMoneyReceivedRequest.php'),
            app_path('Rules'),
            resource_path('lang/ar.json'),
        ])->flatMap(function ($path) {
            if (is_dir($path)) {
                return collect(glob($path.'/*.php'))->map(fn ($f) => file_get_contents($f))->all();
            }

            return [file_get_contents($path)];
        })->implode("\n");

        $missing = [];

        foreach (PageInstructions::keys() as $key) {
            foreach ($this->stringsOf($key) as $string) {
                // Only the strings written as a quoted message, e.g. "Please Select Money Type"
                if (! preg_match_all('/"([A-Z][^"]{10,})"/', $string, $matches)) {
                    continue;
                }
                foreach ($matches[1] as $quoted) {
                    // A quote may name two messages joined by " / ".
                    foreach (explode('" / "', $quoted) as $message) {
                        if (! str_contains($sources, $message)) {
                            $missing[] = $key.': "'.$message.'"';
                        }
                    }
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "%d validation message(s) are quoted in a guide but no longer appear in the app.\n".
            "Either the message was reworded and the guide is now wrong, or the quote has a typo:\n\n  %s\n",
            count($missing), implode("\n  ", $missing)
        ));
    }

    /**
     * A button whose prop is never sent renders with no link, and a
     * prop that is sent to a page that does not declare it is simply
     * undefined — both look fine in a build and fail in the browser.
     * Every screen carrying the button is checked from both ends.
     */
    public function test_every_screen_with_the_button_is_actually_sent_its_link(): void
    {
        $declaring = [];
        foreach ($this->vueFiles() as $file) {
            $source = file_get_contents($file);
            if (! str_contains($source, 'instructionsUrl')) {
                continue;
            }
            $component = str_replace([resource_path('js/Pages').'/', '.vue'], '', $file);
            $declaring[$component] = $source;
        }

        $this->assertNotEmpty($declaring, 'No screen has an Instructions button at all.');

        $controllers = collect(glob(app_path('Http/Controllers/*.php')))
            ->map(fn ($f) => file_get_contents($f))
            ->implode("\n");

        foreach ($declaring as $component => $source) {
            $this->assertMatchesRegularExpression('/^\s*instructionsUrl\s*:/m', $source,
                "{$component}.vue uses instructionsUrl but never declares it in defineProps — it would be undefined.");

            $this->assertMatchesRegularExpression(
                '/render\(\s*[\'"]'.preg_quote($component, '/').'[\'"][^;]{0,400}instructionsUrl/s',
                $controllers,
                "{$component}.vue shows an Instructions button, but no controller sends it an instructionsUrl — the button would have no link."
            );
        }
    }

    /** Every guide must be reachable — a key with no back-link is a dead end. */
    public function test_every_guide_has_a_back_link_of_its_own(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/InstructionsController.php'));

        foreach (PageInstructions::keys() as $key) {
            if (str_starts_with($key, 'settings.') || $key === PageInstructions::FACTORING) {
                continue; // these fall back to home deliberately
            }
            $constant = $this->constantFor($key);
            $this->assertStringContainsString($constant, $controller,
                "InstructionsController has no back-link case for {$key}, so Back would go to the home page.");
        }
    }

    private function constantFor(string $key): string
    {
        $reflection = new \ReflectionClass(PageInstructions::class);
        foreach ($reflection->getConstants() as $name => $value) {
            if ($value === $key) {
                return 'PageInstructions::'.$name;
            }
        }

        return '';
    }

    /** @return list<string> */
    private function vueFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js/Pages')));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
