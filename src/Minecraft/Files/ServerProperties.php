<?php

namespace Wyvern\Minecraft\Files;

/**
 * server.properties, in Java's .properties format.
 *
 * Lines are kept as they were: saving rewrites only the keys that changed, so comments,
 * order and anything the panel does not know about survive a round trip.
 */
final class ServerProperties
{
    /** @var list<array{key: ?string, raw: string}> */
    private array $lines = [];

    /** @var array<string, string> */
    private array $values = [];

    public static function parse(string $content): self
    {
        $properties = new self();

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === '!') {
                $properties->lines[] = ['key' => null, 'raw' => $line];

                continue;
            }

            [$key, $value] = self::split($trimmed);
            $properties->lines[] = ['key' => $key, 'raw' => $line];
            $properties->values[$key] = $value;
        }

        // A trailing newline parses as one empty line; it is written back by render().
        if (end($properties->lines) === ['key' => null, 'raw' => '']) {
            array_pop($properties->lines);
        }

        return $properties;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->values;
    }

    public function set(string $key, string $value): void
    {
        if (($this->values[$key] ?? null) === $value) {
            return;
        }

        $raw = self::escape($key, true) . '=' . self::escape($value, false);

        foreach ($this->lines as $i => $line) {
            if ($line['key'] === $key) {
                $this->lines[$i]['raw'] = $raw;
                $this->values[$key] = $value;

                return;
            }
        }

        $this->lines[] = ['key' => $key, 'raw' => $raw];
        $this->values[$key] = $value;
    }

    public function render(): string
    {
        return implode("\n", array_column($this->lines, 'raw')) . "\n";
    }

    /** @return array{string, string} */
    private static function split(string $line): array
    {
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }

            if ($char === '=' || $char === ':' || $char === ' ' || $char === "\t") {
                $rest = ltrim(substr($line, $i + 1), " \t");

                // "key = value": the separator can be surrounded by whitespace.
                if (($char === ' ' || $char === "\t") && $rest !== '' && ($rest[0] === '=' || $rest[0] === ':')) {
                    $rest = ltrim(substr($rest, 1), " \t");
                }

                return [self::unescape(substr($line, 0, $i)), self::unescape($rest)];
            }
        }

        return [self::unescape($line), ''];
    }

    private static function unescape(string $value): string
    {
        // Characters outside the BMP arrive as a UTF-16 surrogate pair.
        $value = preg_replace_callback('/\\\\u(d[89ab][0-9a-f]{2})\\\\u(d[c-f][0-9a-f]{2})/i', function (array $m): string {
            $code = 0x10000 + ((hexdec($m[1]) - 0xD800) << 10) + (hexdec($m[2]) - 0xDC00);

            return mb_chr((int) $code, 'UTF-8') ?: '';
        }, $value) ?? $value;

        return preg_replace_callback('/\\\\(u[0-9a-fA-F]{4}|.)/s', function (array $m): string {
            $code = $m[1];

            return match (true) {
                $code[0] === 'u' && strlen($code) === 5 => mb_chr((int) hexdec(substr($code, 1)), 'UTF-8'),
                $code === 'n' => "\n",
                $code === 't' => "\t",
                $code === 'r' => "\r",
                $code === 'f' => "\f",
                default => $code,
            };
        }, $value) ?? $value;
    }

    /** Written the way Java's Properties.store() does, so Minecraft reads it back unchanged. */
    private static function escape(string $value, bool $isKey): string
    {
        $out = '';

        foreach (mb_str_split($value) as $i => $char) {
            $code = mb_ord($char);

            $out .= match (true) {
                $char === '\\' => '\\\\',
                $char === "\n" => '\\n',
                $char === "\t" => '\\t',
                $char === "\r" => '\\r',
                $char === "\f" => '\\f',
                $char === '=' || $char === ':' || $char === '#' || $char === '!' => '\\' . $char,
                $char === ' ' && ($isKey || $i === 0) => '\\ ',
                $code > 0xFFFF => sprintf('\\u%04X\\u%04X', 0xD800 + (($code - 0x10000) >> 10), 0xDC00 + (($code - 0x10000) & 0x3FF)),
                $code < 0x20 || $code > 0x7E => sprintf('\\u%04X', $code),
                default => $char,
            };
        }

        return $out;
    }
}
