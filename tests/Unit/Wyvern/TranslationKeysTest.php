<?php

namespace App\Tests\Unit\Wyvern;

use PHPUnit\Framework\TestCase;

/**
 * Guards lang/*\/wyvern.php against duplicate top-level keys.
 *
 * PHP does not complain about a duplicate array key — the last one silently wins and the
 * earlier block simply ceases to exist. This bit three times while building the redesign:
 * 'shortcuts' was already the host link row's, 'version' was already the Version page's,
 * and each time the symptom was a raw translation key appearing in the interface, or worse,
 * working copy quietly replaced by the other block's.
 *
 * A parse of the file catches it in a second, which is considerably cheaper than noticing
 * it in a screenshot.
 */
class TranslationKeysTest extends TestCase
{
    /**
     * The repository root, computed rather than asked for.
     *
     * Laravel's path helper needs a booted application, and booting one to read two files
     * would turn a millisecond parse into a database-backed test for no benefit.
     */
    private function root(string $path = ''): string
    {
        return dirname(__DIR__, 3) . ($path === '' ? '' : '/' . $path);
    }

    public function test_wyvern_translation_files_have_no_duplicate_top_level_keys(): void
    {
        foreach (glob($this->root('lang/*/wyvern.php')) ?: [] as $file) {
            preg_match_all("/^ {4}'([a-z0-9_]+)' => \[/m", (string) file_get_contents($file), $matches);

            $keys = $matches[1];
            $duplicates = array_keys(array_filter(array_count_values($keys), fn (int $n) => $n > 1));

            $this->assertSame(
                [],
                $duplicates,
                sprintf(
                    '%s declares %s more than once. PHP keeps the last and drops the rest, so one of those blocks does not exist at runtime.',
                    str_replace($this->root() . '/', '', $file),
                    implode(', ', array_map(fn (string $k) => "'$k'", $duplicates)),
                ),
            );
        }
    }

    public function test_every_key_referenced_by_wyvern_code_exists(): void
    {
        $translations = require $this->root('lang/en/wyvern.php');

        $sources = array_merge(
            glob($this->root('src/**/*.php')) ?: [],
            glob($this->root('src/**/**/*.php')) ?: [],
            glob($this->root('resources/views/wyvern/**/*.blade.php')) ?: [],
        );

        $missing = [];

        foreach ($sources as $file) {
            preg_match_all("/(?:trans|__)\(\s*'wyvern\.([a-z0-9_.]+)'/", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $key) {
                if (data_get($translations, $key) === null) {
                    $missing[] = $key;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Wyvern code references translation keys that lang/en/wyvern.php does not define.');
    }
}
