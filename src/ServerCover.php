<?php

namespace Wyvern;

use App\Models\Server;

/**
 * Cover art for a server card.
 *
 * The mockups give every server its own illustrated header. Real game key art is
 * licensed material we cannot ship, and only a minority of eggs carry an embedded icon,
 * so the cover is generated instead: a gradient whose hue is derived from the egg, which
 * makes it stable for a given game and different between games without anyone choosing
 * colours by hand.
 *
 * Saturation and lightness are fixed so no cover ever competes with the teal accent or
 * with the status colours sitting on top of it.
 */
final class ServerCover
{
    private const SATURATION = 26;

    private const LIGHTNESS_TOP = 22;

    private const LIGHTNESS_BOTTOM = 11;

    /** Hues that read too close to the accent or to a status colour. */
    private const RESERVED = [[160, 195], [0, 12], [348, 360]];

    public static function gradient(Server $server): string
    {
        $hue = self::hue($server);

        return sprintf(
            'linear-gradient(158deg, hsl(%d %d%% %d%%), hsl(%d %d%% %d%%))',
            $hue, self::SATURATION, self::LIGHTNESS_TOP,
            ($hue + 18) % 360, self::SATURATION, self::LIGHTNESS_BOTTOM,
        );
    }

    /** A brighter pull of the same hue, for the emblem behind the title. */
    public static function tint(Server $server): string
    {
        return sprintf('hsl(%d %d%% 62%%)', self::hue($server), self::SATURATION + 14);
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
