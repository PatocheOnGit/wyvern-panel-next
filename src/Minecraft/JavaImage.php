<?php

namespace Wyvern\Minecraft;

use App\Models\Egg;

/** Maps a required Java major onto one of an egg's Docker images. */
final class JavaImage
{
    /** The egg image with the lowest Java at or above $required. */
    public static function for(Egg $egg, int $required): ?string
    {
        $best = null;

        foreach ($egg->docker_images as $label => $image) {
            $major = self::major($label . ' ' . $image);

            if ($major !== null && $major >= $required && ($best === null || $major < $best[0])) {
                $best = [$major, $image];
            }
        }

        return $best[1] ?? null;
    }

    /** "Java 21" or "yolks:java_21" → 21. */
    public static function major(?string $image): ?int
    {
        return $image && preg_match('/java[\s_-]?(\d+)/i', $image, $m) ? (int) $m[1] : null;
    }
}
