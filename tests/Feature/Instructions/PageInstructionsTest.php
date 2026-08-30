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

    /** Each Money Received screen must actually link to its guide. */
    public function test_each_money_received_screen_links_to_its_guide(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MoneyReceivedController.php'));

        foreach ([
            PageInstructions::MONEY_RECEIVED_INDEX,
            PageInstructions::MONEY_RECEIVED_FORM,
            PageInstructions::MONEY_RECEIVED_DOWN_PAYMENT,
        ] as $key) {
            $constant = 'PageInstructions::'.strtoupper(str_replace(['money-received.', '-'], ['MONEY_RECEIVED_', '_'], $key));
            $this->assertStringContainsString($constant, $controller,
                "MoneyReceivedController does not send an instructionsUrl for {$key}.");
        }

        foreach ([
            'MoneyReceived/Index.vue',
            'MoneyReceived/Form.vue',
            'MoneyReceived/DownPaymentForm.vue',
        ] as $page) {
            $source = file_get_contents(resource_path('js/Pages/'.$page));
            $this->assertMatchesRegularExpression('/^\s*instructionsUrl\s*:/m', $source,
                "{$page} is sent instructionsUrl but does not declare it, so the button would have no link.");
            $this->assertStringContainsString('Instructions', $source,
                "{$page} has no Instructions button.");
        }
    }
}
