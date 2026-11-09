<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Reads the stylesheet and the translation file the way a careful reviewer would, so a broken
 * theme or a half-translated label is caught before anyone sees it.
 *
 * Run: php artisan test --filter=StylesheetAndTranslationsTest
 */
class StylesheetAndTranslationsTest extends TestCase
{
    /** Colours that are deliberately the same in both themes (brand marks on the dark sidebar). */
    private const SAME_IN_BOTH_THEMES = ['--cvr-amber-bright'];

    /** Places allowed to force a style with !important (print / small-screen overrides). */
    private const IMPORTANT_ALLOWED = 3;

    private static function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
    }

    /** @return array<string, string> token => value inside the given selector's block */
    private static function tokensOf(string $selector): array
    {
        $css = self::css();
        $start = strpos($css, $selector.' {');
        self::assertNotFalse($start, "No {$selector} block in app.css");

        $open = strpos($css, '{', $start);
        $depth = 0;
        for ($i = $open; $i < strlen($css); $i++) {
            $depth += $css[$i] === '{' ? 1 : ($css[$i] === '}' ? -1 : 0);
            if ($depth === 0) {
                break;
            }
        }

        $body = preg_replace('#/\*.*?\*/#s', '', substr($css, $open + 1, $i - $open - 1));
        preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $body, $m);

        return array_combine($m[1], array_map('trim', $m[2]));
    }

    public function test_braces_are_balanced(): void
    {
        $css = preg_replace(['#/\*.*?\*/#s', '/"[^"]*"|\'[^\']*\'/'], '', self::css());

        $this->assertSame(substr_count($css, '{'), substr_count($css, '}'));
    }

    public function test_every_light_mode_colour_overrides_a_dark_mode_one(): void
    {
        $unknown = array_diff(array_keys(self::tokensOf('[data-theme="light"]')), array_keys(self::tokensOf(':root')));

        $this->assertSame([], array_values($unknown), 'Light mode sets tokens the dark theme does not define (typo?)');
    }

    /** A colour with no light value keeps its dark-mode shade on a white page. */
    public function test_every_colour_has_a_light_mode_value(): void
    {
        $dark = self::tokensOf(':root');
        $light = self::tokensOf('[data-theme="light"]');

        $missing = [];
        foreach ($dark as $token => $value) {
            $isColour = (bool) preg_match('/^(#[0-9a-f]{3,8}|rgba?\(|hsla?\()/i', $value);

            if ($isColour && ! isset($light[$token]) && ! in_array($token, self::SAME_IN_BOTH_THEMES, true)) {
                $missing[] = $token;
            }
        }

        $this->assertSame([], $missing, 'These colours have no light-mode value');
    }

    public function test_important_is_not_spreading(): void
    {
        $this->assertLessThanOrEqual(self::IMPORTANT_ALLOWED, substr_count(self::css(), '!important'),
            'A new !important — fix the selector instead of forcing it.');
    }

    /* ───────────────────────── resources/lang/ar.json ───────────────────────── */

    private static function arabic(): array
    {
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/lang/ar.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_no_phrase_is_listed_twice(): void
    {
        $raw = (string) file_get_contents(dirname(__DIR__, 2).'/resources/lang/ar.json');
        preg_match_all('/^\s*("(?:[^"\\\\]|\\\\.)*")\s*:/m', $raw, $m);

        $keys = array_map(fn ($k) => json_decode($k), $m[1]);
        $twice = array_keys(array_filter(array_count_values($keys), fn ($n) => $n > 1));

        $this->assertSame([], $twice, 'Listed twice (the second silently wins)');
    }

    public function test_no_translation_is_empty(): void
    {
        $empty = array_keys(array_filter(self::arabic(), fn ($v) => ! is_string($v) || trim($v) === ''));

        $this->assertSame([], $empty);
    }

    /** ":name" in the English phrase must survive into the Arabic, or the value never shows. */
    public function test_placeholders_match_in_both_languages(): void
    {
        $broken = [];
        foreach (self::arabic() as $english => $arabic) {
            preg_match_all('/:([a-zA-Z_]+)/', $english, $en);
            preg_match_all('/:([a-zA-Z_]+)/', (string) $arabic, $ar);

            $missing = array_diff(array_unique($en[1]), array_unique($ar[1]));
            if ($missing !== []) {
                $broken[] = "{$english} → missing :".implode(', :', $missing);
            }

            // The other way round: a placeholder only the Arabic has is never filled in, so the
            // reader sees ":from" literally.
            $unknown = array_diff(array_unique($ar[1]), array_unique($en[1]));
            if ($unknown !== []) {
                $broken[] = "{$english} → Arabic has :".implode(', :', $unknown).' which is never filled in';
            }
        }

        $this->assertSame([], $broken);
    }
}
