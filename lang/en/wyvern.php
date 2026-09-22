<?php

return [
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
        'game' => 'Game',
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

    'permissions' => [
        'minecraft_title' => 'Minecraft',
        'minecraft_desc' => 'Permissions for the Players and Properties pages.',
        'minecraft_players' => 'Manage the whitelist, operators and bans, and kick players.',
        'minecraft_properties' => 'View server.properties as a form. Saving also needs the file update permission.',
    ],

    'backup' => [
        'label' => 'Back up the server first',
        'help' => 'A backup is taken before anything changes, and the rest waits for it to finish.',
        'disabled' => 'Backups are not enabled for this server.',
        'full' => 'This server already has its :limit backups. Delete one to back up first.',
    ],

    'jobs' => [
        'backup_name' => 'Before a change · :date',
        'switch_done' => 'The server has been reinstalled',
        'modpack_done' => ':name installed',
        'done_body' => 'On :server. Start or restart the server to use it.',
        'failed' => 'The change did not finish',
        'failed_body' => 'On :server: :error',
        'unknown_pack_target' => 'This modpack does not say which loader and Minecraft version it needs.',
        'backup_failed' => 'The backup failed, so nothing was changed.',
        'backup_timeout' => 'The backup did not finish within 30 minutes, so nothing was changed.',
        'install_failed' => 'The reinstall failed. The console shows why.',
        'install_timeout' => 'The reinstall did not finish within 30 minutes.',
    ],

    'content' => [
        'title' => 'Content',
        'search_placeholder' => 'Search :source…',
        'downloads' => 'downloads',
        'install' => 'Install',
        'any_version' => 'any version',
        'empty' => 'Nothing here matches this server.',
        'unpinned' => 'The Minecraft version this server runs is not known, so results are not narrowed to one. Check what a file supports before installing it.',
        'modpack_note' => 'A modpack installs in the background, and can switch the server to the loader and version it needs. You get a notification when it finishes.',

        'modes' => [
            'browse' => 'Browse',
            'installed' => 'Installed',
        ],

        'modpack' => [
            'heading' => 'Install :name',
            'fits' => 'Version :version runs on what this server has now, so it is added to the server as it is.',
            'switch' => 'Version :version needs :loaders for Minecraft :minecraft. The server is reinstalled with that first, then the pack is added. Worlds are kept.',
            'replace_mods' => 'Replace the current mods',
            'replace_mods_help' => 'Empties mods/ first. Mods for another loader or version would stop the server from starting.',
        ],

        'installed' => [
            'count' => ':count file in :directory|:count files in :directory',
            'check_updates' => 'Check for updates',
            'updates_found' => 'Everything is up to date|One update available|:count updates available',
            'modpack' => 'From the modpack :name :version',
            'empty' => 'Nothing in :directory yet.',
            'disabled' => 'Disabled',
            'from_modpack' => 'Modpack',
            'manual' => 'Added by hand',
            'update_to' => 'Update to :version',
            'enable' => 'Enable',
            'disable' => 'Disable',
            'delete' => 'Delete',
            'delete_confirm' => 'Delete :file?',
            'restart_note' => 'Changes take effect after a restart. Files added by hand have no update check: the panel does not know where they came from.',
        ],

        'errors' => [
            'not_distributable' => 'The author of this file does not allow it to be downloaded by third-party tools. Install it by hand from the project page.',
            'unknown_loader' => 'This server does not say which loader it runs, so there is no way to tell what would work on it.',
            'wrong_type' => ':loader servers cannot use this kind of content.',
            'not_allowed' => 'You do not have permission to add files to this server.',
            'nothing_to_install' => 'Nothing here can be downloaded for this server.',
            'modpack_loader' => ':pack needs :expected, but this server runs :actual. Switch it on the Version page first.',
            'modpack_version' => ':pack needs Minecraft :expected, but this server runs :actual. Switch it on the Version page first.',
        ],

        'notifications' => [
            'installed' => ':file installed',
            'installed_body' => 'Restart the server for it to load.',
            'failed' => 'Could not install',
            'modpack_queued' => 'Modpack queued',
            'modpack_queued_body' => 'It installs on the node. You get a notification when it finishes.',
        ],
    ],

    'version' => [
        'title' => 'Version',
        'heading' => 'Server version',
        'body' => 'Each flavour is a different server, not a setting. Changing it reinstalls the server files.',
        'latest' => 'Latest',
        'installed' => 'Installed',
        'unknown' => 'Not installed yet',
        'resolved' => ':value (latest)',
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
            'build_help' => 'Paper, Purpur and Folia number their builds. Fabric and Quilt want a loader version. Forge takes recommended or latest.',
        ],

        'java' => [
            'switch' => 'Switch to Java :java',
            'java' => 'Java :java',
            'custom_image' => 'a custom image',
            'needs' => 'Minecraft :version needs Java :required. This server runs :current.',
            'declined_older' => 'Without Java :required the server will almost certainly not start.',
            'declined_newer' => 'Older Minecraft versions often fail on a newer Java. Without Java :required the server will most likely not start.',
            'no_permission' => 'You do not have permission to change this server\'s Docker image.',
        ],

        'errors' => [
            'loader' => 'Pick a server flavour first.',
            'locked' => 'The server flavour is locked for this server.',
        ],

        'unsupported' => [
            'heading' => 'This server cannot be switched from here',
            'body' => 'The :egg egg installs one fixed flavour. Move the server to the Wyvern Minecraft egg to choose between vanilla, Paper, Purpur, Folia, Fabric, Quilt, Forge and NeoForge.',
        ],

        'actions' => [
            'install' => 'Install this version',
            'confirm_heading' => 'Reinstall the server?',
            'confirm_body' => 'The server files are rebuilt from the chosen version. Worlds and configuration are left alone, but anything the installer writes is replaced.',
        ],

        'notifications' => [
            'started' => 'Installing :loader :version',
            'started_body' => 'Watch the console; the server starts once the install finishes.',
            'queued' => 'Backup first, then the install',
            'queued_body' => 'Both run in the background. You get a notification when the install has started.',
            'failed' => 'Could not start the install',
        ],
    ],

    'properties' => [
        'title' => 'Properties',
        'save' => 'Save',
        'missing' => 'This server has no server.properties yet. Start it once and Minecraft writes one.',
        'sparse' => 'Only a few settings are here so far. Start the server once and Minecraft writes all of them.',
        'read_only' => 'You can see these settings but not change them: that needs the file update permission.',
        'managed' => 'Set by the panel from the server\'s allocation:',
        'motd_help' => 'Shown in the multiplayer list. Use § codes for colour, such as §a for green, and a second line for more text.',

        'groups' => [
            'general' => 'General',
            'world' => 'World',
            'players' => 'Players and permissions',
            'resource_pack' => 'Resource pack',
            'performance' => 'Performance and network',
            'remote' => 'Remote access',
            'other' => 'Other',
        ],

        'notifications' => [
            'saved' => 'Properties saved',
            'restart' => 'Restart the server to apply them.',
            'unchanged' => 'Nothing changed',
        ],

        'options' => [
            'gamemode' => ['survival' => 'Survival', 'creative' => 'Creative', 'adventure' => 'Adventure', 'spectator' => 'Spectator'],
            'difficulty' => ['peaceful' => 'Peaceful', 'easy' => 'Easy', 'normal' => 'Normal', 'hard' => 'Hard'],
            'level_type' => [
                'minecraft_normal' => 'Default',
                'minecraft_flat' => 'Superflat',
                'minecraft_large_biomes' => 'Large biomes',
                'minecraft_amplified' => 'Amplified',
                'minecraft_single_biome_surface' => 'Single biome',
            ],
            'op_permission_level' => [
                '1' => '1 · Bypass spawn protection',
                '2' => '2 · Cheat commands',
                '3' => '3 · Player management',
                '4' => '4 · All commands',
            ],
            'function_permission_level' => [
                '1' => '1 · Bypass spawn protection',
                '2' => '2 · Cheat commands',
                '3' => '3 · Player management',
                '4' => '4 · All commands',
            ],
            'region_file_compression' => ['deflate' => 'Deflate (default)', 'lz4' => 'LZ4 (faster, larger)', 'none' => 'None'],
        ],

        'keys' => [
            'motd' => ['label' => 'Message of the day'],
            'max_players' => ['label' => 'Player slots', 'help' => 'How many players can be online at once.'],
            'gamemode' => ['label' => 'Game mode', 'help' => 'The mode new players join in.'],
            'force_gamemode' => ['label' => 'Force game mode', 'help' => 'Put everyone back in the default mode each time they join.'],
            'difficulty' => ['label' => 'Difficulty'],
            'hardcore' => ['label' => 'Hardcore', 'help' => 'Hard difficulty, and players who die are put in spectator mode.'],
            'pvp' => ['label' => 'PvP', 'help' => 'Let players hurt each other.'],
            'online_mode' => ['label' => 'Online mode', 'help' => 'Check every player against Mojang. Turn off only behind a proxy such as Velocity: without it, anyone can join under any name.'],
            'allow_flight' => ['label' => 'Allow flight', 'help' => 'Stop kicking players the server thinks are flying. Needed by some mods and plugins.'],
            'white_list' => ['label' => 'Whitelist', 'help' => 'Only players on the whitelist can join. Managed on the Players page.'],
            'enforce_whitelist' => ['label' => 'Enforce whitelist', 'help' => 'Kick online players who are not on the whitelist when it is reloaded.'],

            'level_name' => ['label' => 'World folder', 'help' => 'Changing this starts a new world; the old one stays on disk.'],
            'level_seed' => ['label' => 'Seed', 'help' => 'Only used when a new world is created. Empty for a random one.'],
            'level_type' => ['label' => 'World type', 'help' => 'Only used when a new world is created.'],
            'generator_settings' => ['label' => 'Generator settings', 'help' => 'JSON for superflat and other custom generators.'],
            'generate_structures' => ['label' => 'Structures', 'help' => 'Generate villages, temples and the like in new chunks.'],
            'allow_nether' => ['label' => 'Nether', 'help' => 'Let players travel to the Nether.'],
            'spawn_monsters' => ['label' => 'Monsters'],
            'spawn_animals' => ['label' => 'Animals'],
            'spawn_npcs' => ['label' => 'Villagers'],
            'spawn_protection' => ['label' => 'Spawn protection', 'help' => 'Radius in blocks around spawn that only operators can change. 0 to turn off.'],
            'max_world_size' => ['label' => 'World border', 'help' => 'Maximum radius of the world, in blocks.'],
            'view_distance' => ['label' => 'View distance', 'help' => 'Chunks sent to each player. The biggest single lever on memory and CPU.'],
            'simulation_distance' => ['label' => 'Simulation distance', 'help' => 'Chunks around each player where mobs and crops keep running.'],
            'initial_enabled_packs' => ['label' => 'Enabled data packs', 'help' => 'Only used when a new world is created.'],
            'initial_disabled_packs' => ['label' => 'Disabled data packs', 'help' => 'Only used when a new world is created.'],

            'player_idle_timeout' => ['label' => 'Idle kick', 'help' => 'Minutes before an idle player is kicked. 0 never kicks.'],
            'op_permission_level' => ['label' => 'Operator level', 'help' => 'What /op gives a player.'],
            'function_permission_level' => ['label' => 'Function level', 'help' => 'What data pack functions may run.'],
            'enable_command_block' => ['label' => 'Command blocks'],
            'hide_online_players' => ['label' => 'Hide player list', 'help' => 'Do not show who is online in the multiplayer list. The Players page then shows only a count.'],
            'enforce_secure_profile' => ['label' => 'Require signed chat', 'help' => 'Only players with a Mojang-signed chat key can join.'],
            'prevent_proxy_connections' => ['label' => 'Block VPNs', 'help' => 'Refuse players whose connection comes from a different address than their login.'],
            'accepts_transfers' => ['label' => 'Accept transfers', 'help' => 'Let other servers send players here with /transfer.'],
            'log_ips' => ['label' => 'Log IP addresses'],
            'broadcast_console_to_ops' => ['label' => 'Show console commands to operators'],
            'bug_report_link' => ['label' => 'Bug report link', 'help' => 'Shown to players who hit an error.'],

            'resource_pack' => ['label' => 'Resource pack URL', 'help' => 'A direct download link to a .zip.'],
            'resource_pack_sha1' => ['label' => 'Resource pack SHA-1', 'help' => 'Lets players keep a cached copy until the pack changes.'],
            'resource_pack_id' => ['label' => 'Resource pack ID'],
            'require_resource_pack' => ['label' => 'Require the resource pack', 'help' => 'Players who decline it are disconnected.'],
            'resource_pack_prompt' => ['label' => 'Resource pack prompt', 'help' => 'Text shown when the pack is offered.'],

            'pause_when_empty_seconds' => ['label' => 'Pause when empty', 'help' => 'Seconds without players before the world stops ticking. 0 or less keeps it running.'],
            'network_compression_threshold' => ['label' => 'Compression threshold', 'help' => 'Packets larger than this many bytes are compressed. -1 turns compression off.'],
            'rate_limit' => ['label' => 'Packet rate limit', 'help' => 'Packets per second before a player is kicked. 0 turns it off.'],
            'max_tick_time' => ['label' => 'Watchdog', 'help' => 'Milliseconds a single tick may take before the server stops itself. -1 turns it off.'],
            'entity_broadcast_range_percentage' => ['label' => 'Entity range', 'help' => 'How far away entities are sent to players, in percent of the default.'],
            'max_chained_neighbor_updates' => ['label' => 'Chained neighbour updates', 'help' => 'Limit on consecutive block updates, against lag machines.'],
            'sync_chunk_writes' => ['label' => 'Synchronous chunk writes', 'help' => 'Safer against crashes, slower to save.'],
            'use_native_transport' => ['label' => 'Native networking', 'help' => 'Linux-optimised networking. Leave on.'],
            'region_file_compression' => ['label' => 'Region compression'],

            'enable_status' => ['label' => 'Show in the server list', 'help' => 'Answer the multiplayer list. Off, the server looks offline there and on the Players page.'],
            'enable_query' => ['label' => 'Query', 'help' => 'The GameSpy4 protocol some server lists use.'],
            'enable_rcon' => ['label' => 'RCON', 'help' => 'Remote console. Needs a port of its own and a strong password.'],
            'rcon_port' => ['label' => 'RCON port'],
            'rcon_password' => ['label' => 'RCON password'],
            'broadcast_rcon_to_ops' => ['label' => 'Show RCON commands to operators'],
            'enable_jmx_monitoring' => ['label' => 'JMX monitoring'],
        ],
    ],

    'players' => [
        'title' => 'Players',
        'online' => 'online',
        'starting' => 'starting, or not answering yet',
        'offline' => 'server offline',
        'offline_hint' => 'The server is stopped, so changes are written to its files and apply at the next start.',
        'whitelist_enabled' => 'Whitelist on',
        'online_now' => 'Online now',
        'recent' => 'Seen recently',
        'no_status' => 'The server did not answer. It may still be starting, or its list may be turned off in Properties.',
        'offline_list' => 'Start the server to see who is online.',
        'hidden' => ':count online. The server hides their names.',
        'nobody' => 'Nobody is online.',
        'no_recent' => 'Nobody has joined yet.',
        'level' => 'Level :level',
        'until' => 'until :date',

        'tabs' => [
            'online' => 'Online',
            'whitelist' => 'Whitelist',
            'ops' => 'Operators',
            'bans' => 'Banned players',
            'ip_bans' => 'Banned IPs',
        ],

        'fields' => [
            'name' => 'Player name',
            'ip' => 'IP address',
            'reason' => 'Reason',
            'reason_optional' => 'Reason (optional)',
        ],

        'add' => [
            'whitelist' => 'Add to whitelist',
            'ops' => 'Make operator',
            'bans' => 'Ban',
            'ip_bans' => 'Ban IP',
        ],

        'remove' => [
            'whitelist' => 'Remove',
            'ops' => 'Remove operator',
            'bans' => 'Unban',
            'ip_bans' => 'Unban',
        ],

        'empty' => [
            'whitelist' => 'The whitelist is empty.',
            'ops' => 'No operators.',
            'bans' => 'Nobody is banned.',
            'ip_bans' => 'No IP address is banned.',
        ],

        'badges' => [
            'op' => 'Operator',
            'banned' => 'Banned',
        ],

        'actions' => [
            'whitelist' => 'Whitelist',
            'unwhitelist' => 'Unwhitelist',
            'op' => 'Op',
            'deop' => 'Deop',
            'kick' => 'Kick',
            'ban' => 'Ban',
            'pardon' => 'Unban',
            'kick_heading' => 'Kick :name?',
            'ban_heading' => 'Ban :name?',
        ],

        'errors' => [
            'failed' => 'That did not work',
            'name' => 'A player name is 1 to 16 letters, digits or underscores.',
            'ip' => 'That is not an IP address.',
            'unknown_account' => 'There is no Minecraft account named :name.',
        ],
    ],

    'console_fixes' => [
        'open_properties' => 'Open Properties',
        'open_content' => 'Find it in Content',
        'open_installed' => 'Open installed content',
        'open_version' => 'Open Version',

        'port' => [
            'heading' => 'The port is already in use',
            'description' => 'Another process holds port :port, often a previous copy of this server that has not finished stopping. Restart once; if it happens again, check that no other server uses the same port.',
        ],
        'memory' => [
            'heading' => 'The server ran out of memory',
            'description' => 'It has :memory. Lower the view and simulation distances, remove heavy mods or plugins, or ask for more memory.',
            'unlimited' => 'no memory limit',
        ],
        'dependency' => [
            'heading' => 'A mod is missing a dependency',
            'description' => 'These are required by other mods and are not installed:',
            'unknown' => 'A mod needs another one that is not installed. The console names it.',
        ],
        'client_mod' => [
            'heading' => 'A client-only mod is installed',
            'description' => 'A mod meant for the game itself, not a server, stopped the start. These were named near the error:',
            'unknown' => 'A mod meant for the game itself, not a server, stopped the start. Disable mods one at a time to find it.',
        ],
        'world' => [
            'heading' => 'The world is from a newer version',
            'description' => 'This world was saved by a newer Minecraft than the one installed. Install that version again, or restore a backup made before the upgrade.',
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
