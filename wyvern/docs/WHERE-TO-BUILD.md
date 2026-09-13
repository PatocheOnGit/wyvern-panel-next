# What the panel already gives us, and where Wyvern features belong

Written 2026-09-13 after reading the extension points rather than guessing at them.
Nothing here has been implemented — it is a map of the seams.

The question behind it: the founding constraint was "plugins over core modification",
but Pelican ships several *core* extension points that are designed to be extended.
Adding a class to one of those is not core modification in the bad sense — it is using
the seam the framework provides, and it rebases cleanly.

## The seams that already exist

### Features — reactive console help

`app/Extensions/Features/Schemas/`, five classes today: `MinecraftEulaSchema`,
`JavaVersionSchema`, `PIDLimitSchema`, `GSLTokenSchema`, `SteamDiskSpaceSchema`.

The mechanism is small and good. A schema declares substrings to watch for in console
output, and a Filament action to raise when one appears:

```php
public function getListeners(): array   // ['you need to agree to the eula ...']
public function getId(): string
public function authorize(User $user, Server $server): bool
public function getAction(): Action     // modal + the fix
```

`MinecraftEulaSchema` watches for the EULA complaint, writes `eula.txt` and restarts.
Worth knowing: during setup the EULA was accepted by hand with a shell redirect into
the volume. That was unnecessary — the panel already does it, one click, from the
console.

**This framework is underpopulated and it is the cheapest place to add value.** Each
schema is roughly sixty lines and needs no migration, no model and no route.

### Tasks — schedule actions

`app/Extensions/Tasks/Schemas/`: `PowerActionSchema`, `SendCommandSchema`,
`CreateBackupSchema`, `DeleteFilesSchema`. The interface covers the whole lifecycle —
`getId`, `getName`, `runTask`, `canCreate`, `getDefaultPayload`, `getPayloadForm`. A new
scheduled action is one class.

### The rest

| Seam | State |
|---|---|
| `OAuth/Schemas` | 13 providers — Discord, Steam, Authentik, GitHub, Google… |
| `Captcha`, `Avatar`, `BackupAdapter` | schema-driven, same shape |
| Webhooks | events, subscriptions, an admin resource |
| `SubuserPermission` | 44 fine-grained permissions already defined |

## What Wings offers that the panel does not spend

The panel's daemon repositories cover files, backups, power, transfers and install
logs. `files/pull` is already wired — `ListFiles` has a pull-from-URL action — and so
are `files/decompress`, `files/search` and `/commands`.

The gap is at node level. `DaemonSystemRepository` calls `/api/system` and nothing
else, while Wings also exposes:

| Endpoint | What it would give us |
|---|---|
| `/api/system/utilization` | live node CPU, memory and disk, rather than per-server aggregates |
| `/api/system/docker/disk` | how much of the node's disk Docker images and volumes actually hold |
| `/api/system/docker/image/prune` | a prune button; `p:maintenance:prune-images` already does this on a schedule, invisibly |
| `/api/system/ips` | the node's real addresses, useful when allocations are set up by hand |

The node page already has CPU, memory and storage charts, so the rendering exists. It
is the data source that is thinner than it needs to be.

## Where each Wyvern feature belongs

### In core, because a seam already fits

1. **More Feature schemas.** The obvious ones from operating a server: port already in
   use, container OOM-killed, Java version mismatched to the Minecraft version, missing
   mod dependency on startup, corrupted world. Each turns a confusing console dump into
   one button.
2. **More Task schemas.** Restart when empty, prune old logs, pull a file from a URL on
   a schedule (which is a modpack update, expressed in existing vocabulary).
3. **Node health.** Wire the four endpoints above into the node page and give prune a
   button.
4. **The Big 4 hook into the same place.** FiveM, Garry's Mod and Hytale each get a
   Feature schema for their common startup failures. Nothing bespoke is needed — the
   egg's `features:` list is already the generic dispatch, which is exactly the
   game-agnostic architecture the project asked for.

### As a plugin, because it needs its own everything

1. **Modpack browser.** CurseForge and Modrinth search, version pinning, install.
   Needs API keys, its own tables, its own page. The transport is already there —
   `files/pull` then `files/decompress` — but the surface around it is a product.
2. **Player management.** Whitelist, bans, connection history, playtime. Needs its own
   tables and a per-game adapter. Nothing in the panel touches this today.
3. Anything billing-shaped.

## Two improvements that are not about games at all

**Egg and server deletion are not written to the activity log.** Power actions are;
deletions are not. When a server vanished during setup, the log recorded the
`power.start` and nothing else, which made a two-minute question into a twenty-minute
one. `servers.egg_id` is a foreign key onto `eggs`, so removing an egg silently takes
its servers with it — a destructive cascade with no audit trail. Adding those two
events is small, obviously correct, and the kind of thing upstream would take.

**The storage symlink is not checked.** Egg icons silently 404 until someone runs
`php artisan storage:link`. Pelican already depends on `spatie/laravel-health` and runs
`health:check` every five minutes; a check for `public/storage` belongs in it. This bit
us today and the symptom — blank icons — points nowhere near the cause.
