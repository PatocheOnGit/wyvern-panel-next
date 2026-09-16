<?php

namespace Wyvern;

use App\Models\Server;

/**
 * The per-game identity of a server card.
 *
 * Real game key art is licensed material we cannot ship, and only about fifty of the
 * three hundred-odd eggs carry an embedded icon, so the identity is generated. It used
 * to be a full-bleed gradient occupying the top 40% of every card — which meant three
 * hundred hues competing with the one thing the card exists to say, whether the server
 * is running. Under the palette rule (colour means a state) that is exactly backwards.
 *
 * So the hue survives, and the surface it paints does not: it is now a three-pixel edge
 * on the card. Stable per game, different between games, recognisable down a column of
 * twenty cards — and worth no saturation anywhere the status lives.
 */
final class ServerCover
{
    /** Enough chroma to tell two games apart, not enough to read as a status. */
    private const SATURATION = 42;

    private const LIGHTNESS = 52;

    /** Hues that read too close to a status colour to be used as decoration. */
    private const RESERVED = [[100, 165], [0, 14], [340, 360]];

    /** The edge that identifies the game. */
    public static function stripe(Server $server): string
    {
        return sprintf('hsl(%d %d%% %d%%)', self::hue($server), self::SATURATION, self::LIGHTNESS);
    }

    /** The same hue, quietened, for the plate an emblem sits on. */
    public static function plate(Server $server): string
    {
        return sprintf('hsl(%d %d%% %d%% / 0.14)', self::hue($server), self::SATURATION, self::LIGHTNESS);
    }

    /** A brighter pull of it, for the initials shown when an egg has no icon. */
    public static function tint(Server $server): string
    {
        return sprintf('hsl(%d %d%% 62%%)', self::hue($server), self::SATURATION);
    }

    private static function hue(Server $server): int
    {
        $seed = $server->egg?->uuid ?? $server->egg?->name ?? $server->uuid;
        $hue = hexdec(substr(md5((string) $seed), 0, 4)) % 360;

        foreach (self::RESERVED as [$from, $to]) {
            if ($hue >= $from && $hue <= $to) {
                $hue = ($to + 24) % 360;
            }
        }

        return $hue;
    }
}
