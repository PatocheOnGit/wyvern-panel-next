<?php

return [
    /*
     * The shortcut row on the client home.
     *
     * An entry without a url is skipped, so the row shows only what has actually been
     * set up rather than a wall of dead links. Tone picks the tint behind the icon and
     * must be one of: brand, accent, positive, caution.
     */
    'shortcuts' => [
        [
            'icon' => 'discord',
            'tone' => 'brand',
            'label' => 'Discord',
            'description' => 'Support et communauté',
            'url' => env('WYVERN_DISCORD_URL'),
        ],
        [
            'icon' => 'book',
            'tone' => 'accent',
            'label' => 'Documentation',
            'description' => 'Guides et dépannage',
            'url' => env('WYVERN_DOCS_URL', 'https://github.com/PatocheOnGit/wyvern-panel'),
        ],
        [
            'icon' => 'pulse',
            'tone' => 'positive',
            'label' => 'Statut',
            'description' => 'État des services',
            'url' => env('WYVERN_STATUS_URL'),
        ],
        [
            'icon' => 'star',
            'tone' => 'caution',
            'label' => 'Boutique',
            'description' => 'Étendre vos ressources',
            'url' => env('WYVERN_STORE_URL'),
        ],
    ],
];
