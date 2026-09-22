<?php

namespace Wyvern\Minecraft\Properties;

/** Renders a MOTD's § formatting codes as HTML, the way the multiplayer screen does. */
final class Motd
{
    /** Minecraft's own sixteen chat colours: game data, not theme. */
    private const COLOURS = [
        '0' => '#000000', '1' => '#0000AA', '2' => '#00AA00', '3' => '#00AAAA',
        '4' => '#AA0000', '5' => '#AA00AA', '6' => '#FFAA00', '7' => '#AAAAAA',
        '8' => '#555555', '9' => '#5555FF', 'a' => '#55FF55', 'b' => '#55FFFF',
        'c' => '#FF5555', 'd' => '#FF55FF', 'e' => '#FFFF55', 'f' => '#FFFFFF',
    ];

    private const STYLES = [
        'l' => 'font-weight:700',
        'm' => 'text-decoration:line-through',
        'n' => 'text-decoration:underline',
        'o' => 'font-style:italic',
    ];

    public static function html(string $motd): string
    {
        $lines = array_slice(explode("\n", $motd), 0, 2);

        return implode('<br>', array_map(self::line(...), $lines));
    }

    private static function line(string $line): string
    {
        $parts = preg_split('/(§[0-9a-fk-or])/iu', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $colour = self::COLOURS['7'];
        $styles = [];
        $html = '';

        foreach ($parts as $part) {
            if (preg_match('/^§([0-9a-fk-or])$/iu', $part, $m)) {
                $code = strtolower($m[1]);

                if (isset(self::COLOURS[$code])) {
                    // A colour code resets formatting, as it does in game.
                    $colour = self::COLOURS[$code];
                    $styles = [];
                } elseif ($code === 'r') {
                    $colour = self::COLOURS['7'];
                    $styles = [];
                } elseif (isset(self::STYLES[$code])) {
                    $styles[$code] = self::STYLES[$code];
                }

                continue;
            }

            $style = implode(';', ['color:' . $colour, ...array_values($styles)]);
            $html .= '<span style="' . $style . '">' . e($part) . '</span>';
        }

        return $html === '' ? '&nbsp;' : $html;
    }
}
