<?php

return [
    /*
     * Sidebar groups for the server panel.
     *
     * The panel shipped fourteen destinations in one flat list, with three of them
     * colliding on the same sort value. Grouping them is the difference between
     * scanning a list and reading a menu. Console and Files stay ungrouped at the top
     * because they are where people actually spend their time.
     */
    /*
     * The admin dashboard. Phrased so the good case is as short as the bad one is
     * specific — an operator scanning four tiles should be able to stop reading as soon
     * as everything says it is fine.
     */
    /*
     * The command palette. "Jump to" rather than "Search", because it does not search
     * records — it goes to pages and servers, and saying so avoids promising otherwise.
     */
    'api_docs' => [
        'title' => 'API documentation',
        'lead' => 'Two APIs, with separate keys and separate scopes.',
        'application' => 'Application API',
        'application_hint' => 'Administrative: servers, nodes, users, eggs.',
        'client' => 'Client API',
        'client_hint' => 'What a server owner can do to their own servers.',
        'note' => 'Both require you to be signed in to this panel.',
    ],

    'pins' => [
        'pin' => 'Pin this server',
        'unpin' => 'Unpin this server',
    ],

    'density' => [
        'label' => 'Density',
        'compact' => 'Compact',
        'comfortable' => 'Comfortable',
    ],

    'keyboard' => [
        'title' => 'Keyboard shortcuts',
        'general' => 'General',
        'jump' => 'Go to',
        'close' => 'Close a dialog',
        'armed' => 'waiting for a letter',
    ],

    'palette' => [
        'label' => 'Jump to a page or server',
        'trigger' => 'Jump to',
        'shortcut' => 'K',
        'placeholder' => 'Jump to a page or server…',
        'empty' => 'Nothing matches that.',
        'pages' => 'Page',
        'servers' => 'Server',
    ],

    'release' => [
        'line' => 'Wyvern :version  ·  :upstream :upstreamVersion',
    ],

    'updates' => [
        'unknown_heading' => 'Version not verified',
        'unknown_canary' => 'This install is a canary build (:version), so there is no release number to compare it against. Check it against the commits in :repository.',
        'unknown_unreachable' => 'Could not ask :repository for its latest release. A private repository needs WYVERN_UPDATE_TOKEN set; a repository that publishes no releases has nothing to compare against.',
        'open_repository' => 'Open repository',
    ],

    'dashboard' => [
        'servers' => 'Servers',
        'servers_ok' => 'All in service',
        'servers_attention' => ':suspended suspended, :failed failed to install',
        'nodes' => 'Nodes',
        'nodes_ok' => 'All in service',
        'nodes_maintenance' => ':count in maintenance',
        'memory' => 'Memory allocated',
        'disk' => 'Disk allocated',
        'of_capacity' => ':percent% of :capacity',
        'no_ceiling' => 'No configured ceiling',
        'node_health' => 'Nodes',
        'recent_activity' => 'Recent activity',
        'no_activity' => 'Nothing has happened yet',
    ],

    'navigation' => [
        'software' => 'Software',
        'data' => 'Data',
        'automation' => 'Automation',
        'access' => 'Access',
        'configuration' => 'Configuration',
        'more' => 'More',
        'mobile_label' => 'Quick navigation',
    ],

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

    'database_access' => [
        'title' => 'Open a database',
        'heading' => 'Sign in to phpMyAdmin',
        'description' => 'Pick one of your databases and give its password. phpMyAdmin opens signed in as that database\'s own MySQL user, so it shows that database and nothing else.',

        'fields' => [
            'database' => 'Database',
            'database_help' => 'Signs in as :username on :host.',
            'password' => 'Database password',
            'password_help' => 'The password of the database itself, not your panel password. It is on the server\'s Databases page.',
        ],

        'actions' => [
            'open' => 'Open in phpMyAdmin',
        ],

        'empty' => [
            'heading' => 'No databases yet',
            'body' => 'Databases are created per server, on a server\'s Databases page. Once one exists it can be opened from here.',
        ],

        'notifications' => [
            'gone' => 'That database is no longer available to you',
            'no_host' => 'That database has no host configured',
            'refused' => 'MySQL refused those credentials',
            'refused_body' => 'The database password is the one shown on the server\'s Databases page, not your panel password.',
        ],
    ],
];
