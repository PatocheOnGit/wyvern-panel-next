<?php

namespace Wyvern\FiveM;

/**
 * server.cfg, as FXServer reads it: one command per line, "#" or "//" comments.
 *
 * Keys are the command, or the command and its variable for set/sets/setr ("sets tags").
 * Edits rewrite only the lines they touch; everything else keeps its place.
 */
final class ServerCfg
{
    private const SETTERS = ['set', 'sets', 'setr'];

    /** @var list<string> */
    private array $lines;

    private function __construct(string $content)
    {
        $this->lines = preg_split('/\r\n|\r|\n/', rtrim($content, "\r\n")) ?: [];
    }

    public static function parse(string $content): self
    {
        return new self($content);
    }

    public function get(string $key): ?string
    {
        $value = null;

        foreach ($this->lines as $line) {
            [$lineKey, $lineValue] = self::entry($line) ?? [null, null];

            if ($lineKey !== null && strcasecmp($lineKey, $key) === 0) {
                $value = $lineValue;
            }
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return $this->index($key) !== null;
    }

    public function set(string $key, string $value): void
    {
        $line = $key . ' ' . self::quote($value);
        $index = $this->index($key);

        if ($index === null) {
            $this->lines[] = $line;
        } else {
            $this->lines[$index] = $line;
        }
    }

    /** A convar written either way: "sv_maxclients 48" or "set sv_maxclients 48". */
    public function convar(string $name): ?string
    {
        return $this->get('set ' . $name) ?? $this->get($name);
    }

    /** Always as "set name value": Enhanced has no bare-convar commands. */
    public function setConvar(string $name, string $value): void
    {
        if ($this->has($name) && !$this->has('set ' . $name)) {
            $this->lines[(int) $this->index($name)] = 'set ' . $name . ' ' . self::quote($value);

            return;
        }

        $this->remove($name);
        $this->set('set ' . $name, $value);
    }

    public function removeConvar(string $name): void
    {
        $this->remove($name);
        $this->remove('set ' . $name);
    }

    public function remove(string $key): void
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            fn (string $line) => strcasecmp((string) (self::entry($line)[0] ?? ''), $key) !== 0,
        ));
    }

    /** @return list<string> resources and [categories] started by ensure or start */
    public function ensured(): array
    {
        $names = [];

        foreach ($this->lines as $line) {
            $tokens = self::tokens($line);

            if (in_array(strtolower($tokens[0] ?? ''), ['ensure', 'start'], true) && isset($tokens[1])) {
                $names[] = $tokens[1];
            }
        }

        return array_values(array_unique($names));
    }

    public function setEnsured(string $resource, bool $ensured): void
    {
        $matches = fn (string $line) => in_array(strtolower(self::tokens($line)[0] ?? ''), ['ensure', 'start'], true)
            && strcasecmp(self::tokens($line)[1] ?? '', $resource) === 0;

        if (!$ensured) {
            $this->lines = array_values(array_filter($this->lines, fn ($line) => !$matches($line)));

            return;
        }

        if (collect($this->lines)->contains($matches)) {
            return;
        }

        // Next to the other ensures, so the file keeps reading in order.
        $last = null;
        foreach ($this->lines as $i => $line) {
            if (in_array(strtolower(self::tokens($line)[0] ?? ''), ['ensure', 'start'], true)) {
                $last = $i;
            }
        }

        if ($last === null) {
            $this->lines[] = 'ensure ' . $resource;
        } else {
            array_splice($this->lines, $last + 1, 0, ['ensure ' . $resource]);
        }
    }

    public function render(): string
    {
        return implode("\n", $this->lines) . "\n";
    }

    private function index(string $key): ?int
    {
        $found = null;

        foreach ($this->lines as $i => $line) {
            if (strcasecmp((string) (self::entry($line)[0] ?? ''), $key) === 0) {
                $found = $i;
            }
        }

        return $found;
    }

    /** @return array{string, string}|null */
    private static function entry(string $line): ?array
    {
        $tokens = self::tokens($line);

        if ($tokens === []) {
            return null;
        }

        if (in_array(strtolower($tokens[0]), self::SETTERS, true) && isset($tokens[1])) {
            return [strtolower($tokens[0]) . ' ' . $tokens[1], $tokens[2] ?? ''];
        }

        return [$tokens[0], $tokens[1] ?? ''];
    }

    /** @return list<string> */
    private static function tokens(string $line): array
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
            return [];
        }

        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|(\S+)/', $line, $m, PREG_SET_ORDER);

        $tokens = [];
        foreach ($m as $match) {
            if (($match[2] ?? '') !== '' && (str_starts_with($match[2], '#') || str_starts_with($match[2], '//'))) {
                break;
            }
            $tokens[] = ($match[2] ?? '') !== '' ? $match[2] : stripcslashes($match[1]);
        }

        return $tokens;
    }

    private static function quote(string $value): string
    {
        return preg_match('/^[A-Za-z0-9_.:\/-]+$/', $value) === 1
            ? $value
            : '"' . addcslashes($value, '"\\') . '"';
    }
}
