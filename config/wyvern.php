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
     * The Pelican release this tree is rebased on.
     *
     * Recorded here rather than encoded into the version number. See
     * wyvern/docs/VERSIONING.md for why: version_compare() would rank an upstream bump
     * above our own work, so a rebase would read as several Wyvern releases being undone.
     *
     * Update it when you rebase. It is shown next to Wyvern's own version everywhere the
     * version appears, which is the whole point — both numbers visible, each moving for
     * its own reason.
     */
    'upstream' => [
        'project' => 'Pelican',
        'repository' => 'pelican-dev/panel',
        'version' => 'v1.0.0-beta38',
    ],

    /*
     * Where the panel looks to find out whether it is out of date.
     *
     * Upstream asked GitHub about pelican/panel, which tells a Wyvern operator nothing.
     * It asks about this repository instead — but note the API needs a token for a
     * private one, exactly like the egg index does, and it needs the repository to
     * publish releases. Without either the dashboard says it could not check, which is
     * the truth, rather than claiming to be up to date.
     */
    'updates' => [
        'repository' => env('WYVERN_UPDATE_REPOSITORY', 'PatocheOnGit/wyvern-panel'),
        'token' => env('WYVERN_UPDATE_TOKEN'),
    ],

    /*
     * Where mods, plugins and modpacks are searched.
     *
     * Modrinth needs no key but asks clients to identify themselves, so the user agent
     * carries a contact. A repository URL rather than an email address: this file is
     * public, and a mailbox in a public repository is a mailbox in every scraper's list. CurseForge needs a key their docs say a third-party
     * service must apply for; without one the source hides itself rather than offering
     * a tab that answers 403.
     */
    'content' => [
        'user_agent' => env(
            'WYVERN_CONTENT_USER_AGENT',
            'PatocheOnGit/wyvern-panel (+https://github.com/PatocheOnGit/wyvern-panel)',
        ),
        'curseforge_key' => env('WYVERN_CURSEFORGE_KEY'),

        // A big pack is hundreds of megabytes and dozens of files; the node is
        // doing the work, but it still has to finish before the next step.
        'modpack_timeout' => (int) env('WYVERN_MODPACK_TIMEOUT', 900),
    ],
];
