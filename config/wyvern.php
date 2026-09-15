<?php

use App\Enums\TablerIcon;

return [
    /*
     * The shortcut row on the client home.
     *
     * An entry without a url is skipped, so the row shows only what has actually been
     * set up rather than a wall of dead links. Wording lives in lang/en/wyvern.php and
     * is resolved from the id, so this file holds no prose. Tone picks the tint behind
     * the icon: brand, accent, positive or caution.
     */
    'shortcuts' => [
        [
            'id' => 'discord',
            'icon' => TablerIcon::BrandDiscord->value,
            'tone' => 'brand',
            'url' => env('WYVERN_DISCORD_URL'),
        ],
        [
            'id' => 'docs',
            'icon' => TablerIcon::Book->value,
            'tone' => 'accent',
            'url' => env('WYVERN_DOCS_URL', 'https://github.com/PatocheOnGit/wyvern-panel'),
        ],
        [
            'id' => 'status',
            'icon' => TablerIcon::ActivityHeartbeat->value,
            'tone' => 'positive',
            'url' => env('WYVERN_STATUS_URL'),
        ],
        [
            'id' => 'store',
            'icon' => TablerIcon::ShoppingBag->value,
            'tone' => 'caution',
            'url' => env('WYVERN_STORE_URL'),
        ],
    ],

    /*
     * Where mods, plugins and modpacks are searched.
     *
     * Modrinth needs no key but asks clients to identify themselves, so the user agent
     * carries a contact address. CurseForge needs a key their docs say a third-party
     * service must apply for; without one the source hides itself rather than offering
     * a tab that answers 403.
     */
    'content' => [
        'user_agent' => env(
            'WYVERN_CONTENT_USER_AGENT',
            'PatocheOnGit/wyvern-panel (254776717+PatocheOnGit@users.noreply.github.com)',
        ),
        'curseforge_key' => env('WYVERN_CURSEFORGE_KEY'),
    ],
];
