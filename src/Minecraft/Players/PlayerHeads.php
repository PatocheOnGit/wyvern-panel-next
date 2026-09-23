<?php

namespace Wyvern\Minecraft\Players;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A player's face, cut from their skin by the panel itself.
 *
 * Only Mojang's own API is asked, so no third-party head service learns who plays here.
 */
class PlayerHeads
{
    private const SIZE = 64;

    /** PNG bytes, or null for an unknown or offline-mode name. */
    public function png(string $name): ?string
    {
        $cached = Cache::remember('wyvern.head.' . strtolower($name), now()->addDay(), fn () => $this->render($name) ?? '');

        return $cached === '' ? null : $cached;
    }

    private function render(string $name): ?string
    {
        $id = Http::timeout(5)->get('https://api.mojang.com/users/profiles/minecraft/' . rawurlencode($name))->json('id');

        if (!is_string($id)) {
            return null;
        }

        $textures = Http::timeout(5)->get("https://sessionserver.mojang.com/session/minecraft/profile/$id")->json('properties.0.value');
        $skinUrl = is_string($textures) ? (json_decode(base64_decode($textures), true)['textures']['SKIN']['url'] ?? null) : null;

        if (!is_string($skinUrl) || parse_url($skinUrl, PHP_URL_HOST) !== 'textures.minecraft.net') {
            return null;
        }

        $skin = @imagecreatefromstring((string) Http::timeout(5)->get($skinUrl)->body());

        if ($skin === false) {
            return null;
        }

        $head = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($head, true);
        imagesavealpha($head, true);
        imagefill($head, 0, 0, imagecolorallocatealpha($head, 0, 0, 0, 127));

        // Face at (8,8), then the hat layer at (40,8) over it; nearest-neighbour keeps the pixels square.
        imagecopyresized($head, $skin, 0, 0, 8, 8, self::SIZE, self::SIZE, 8, 8);

        if (self::hatHasTransparency($skin)) {
            imagecopyresized($head, $skin, 0, 0, 40, 8, self::SIZE, self::SIZE, 8, 8);
        }

        ob_start();
        imagepng($head);

        return (string) ob_get_clean();
    }

    /** Old 64x32 skins fill the hat layer solid; the game ignores it then, and so do we. */
    private static function hatHasTransparency(\GdImage $skin): bool
    {
        for ($x = 40; $x < 48; $x++) {
            for ($y = 8; $y < 16; $y++) {
                if (imagecolorsforindex($skin, imagecolorat($skin, $x, $y))['alpha'] > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
