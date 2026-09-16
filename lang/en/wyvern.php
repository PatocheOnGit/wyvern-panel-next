<?php

return [
    'shortcuts' => [
        'discord' => [
            'label' => 'Discord',
            'description' => 'Support and community',
        ],
        'docs' => [
            'label' => 'Documentation',
            'description' => 'Guides and troubleshooting',
        ],
        'status' => [
            'label' => 'Status',
            'description' => 'Service health',
        ],
        'store' => [
            'label' => 'Store',
            'description' => 'Add more resources',
        ],
    ],

    'content' => [
        'title' => 'Content',
        'search_placeholder' => 'Search Modrinth…',
        'downloads' => 'downloads',
        'install' => 'Install',
        'any_version' => 'any version',
        'empty' => 'Nothing here matches this server.',
        'unpinned' => 'This server does not pin a Minecraft version, so results are not narrowed to one. Check what a file supports before installing it.',
        'modpack_note' => 'A modpack is an archive plus its mods and config, so it installs in the background. Watch the file manager, and restart the server once it settles.',

        'errors' => [
            'not_distributable' => 'The author of this file does not allow it to be downloaded by third-party tools. Install it by hand from the project page.',
            'unknown_loader' => 'This server does not say which loader it runs, so there is no way to tell what would work on it.',
            'wrong_type' => ':loader servers cannot use this kind of content.',
            'not_allowed' => 'You do not have permission to add files to this server.',
            'nothing_to_install' => 'Nothing here can be downloaded for this server.',
        ],

        'notifications' => [
            'installed' => ':file installed',
            'installed_body' => 'Restart the server for it to load.',
            'failed' => 'Could not install',
            'modpack_queued' => 'Modpack queued',
            'modpack_queued_body' => 'It downloads on the node. Watch the file manager, and restart the server once it settles.',
        ],
    ],

    'version' => [
        'title' => 'Version',
        'heading' => 'Server version',
        'body' => 'Each flavour is a different server, not a setting. Changing it reinstalls the server files.',
        'latest' => 'Latest',
        'installed' => 'Installed',
        'unknown' => 'Not installed yet',
        'plan' => 'Will install :loader :version, build :build.',
        'up_to_date' => 'This is what the server already runs. Installing again rebuilds it from the same source.',

        'steps' => [
            'flavour' => 'Flavour',
            'version' => 'Version',
            'version_help' => 'Both lists come from the flavour’s own upstream API, so they are whatever is published today.',
        ],

        'fields' => [
            'loader' => 'Flavour',
            'version' => 'Minecraft version',
            'build' => 'Build',
            'build_help' => 'Paper and Purpur number their builds. Fabric wants a loader version. Forge takes recommended or latest.',
        ],

        'unsupported' => [
            'heading' => 'This server cannot be switched from here',
            'body' => 'The :egg egg installs one fixed flavour. Move the server to the Wyvern Minecraft egg to choose between vanilla, Paper, Purpur, Fabric, Forge and NeoForge.',
        ],

        'actions' => [
            'install' => 'Install this version',
            'confirm_heading' => 'Reinstall the server?',
            'confirm_body' => 'The server files are rebuilt from the chosen version. Worlds and configuration are left alone, but anything the installer writes is replaced.',
        ],

        'notifications' => [
            'started' => 'Installing :loader :version',
            'started_body' => 'Watch the console; the server starts once the install finishes.',
            'failed' => 'Could not start the install',
        ],
    ],
];
