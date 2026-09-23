# One Minecraft egg, six flavours

Pick vanilla, Paper, Purpur, Fabric, Forge or NeoForge at install time. Nothing is
hardcoded: each flavour resolves against its own official API, so the version list is
whatever upstream publishes today.

## The two halves

**`Wyvern\Minecraft`** in the panel answers "what can I install?" — it is what a picker
reads. **The egg's install script** answers "fetch it" — it runs in a container on the
node, with no panel, so it calls the same APIs itself. Both exist on purpose; neither
can do the other's job.

| Flavour | Source | Shape |
|---|---|---|
| Vanilla | `launchermeta.mojang.com` | manifest, then a per-version document holding the server jar |
| Paper | `fill.papermc.io/v3` | builds carry a download entry and a sha256 |
| Purpur | `api.purpurmc.org/v2` | stable download url, no lookup needed |
| Fabric | `meta.fabricmc.net/v2` | assembles a launcher from game + loader + installer versions |
| Forge | `promotions_slim.json` | only `recommended` and `latest` exist per version |
| NeoForge | Maven metadata | version list only; the Minecraft version is encoded in the string |

`php artisan wyvern:mc:check` resolves a download for every flavour and HEAD-requests
it. That is the test — six third-party APIs are not something to trust fixtures about.

## Two things the APIs do not tell you

**Forge and NeoForge hand out an installer, not a server jar.** It has to run once on
the node. Since 1.17 it does not even produce a runnable jar: it writes `libraries/`
plus a `unix_args.txt` full of classpath arguments. So the install script runs the
installer, links that file into the server root, and the startup command branches on
whether it exists:

```
java … $( [[ -f user_jvm_args.txt ]] && printf %s "@user_jvm_args.txt " )$( [[ ! -f unix_args.txt ]] && printf %s "-jar {{SERVER_JARFILE}}" || printf %s "@unix_args.txt" ) nogui
```

That is the whole trick behind "install a mod loader, press start, it runs" with nobody
editing a startup command. `user_jvm_args.txt` is picked up when present so flags added
there take effect, which is what the installer's own `run.sh` does.

**NeoForge never states which Minecraft version a build targets.** It encodes it, under
two schemes at once, and writes "no patch" as a zero where Mojang writes nothing. The
rule — drop the build, then drop a trailing `.0` — was derived by checking candidates
against Mojang's manifest, not assumed. Every version the catalogue offers exists there.

## What was actually run

All six flavours through the real script in the real install container. NeoForge end to
end on the live node: installer ran, launcher linked, `Done (0.467s)!`, port listening.

One thing to know: starting a freshly installed server within seconds of the install
finishing can crash once. The installer leaves files root-owned and Wings chowns them on
boot; start into that window and the JVM cannot read its own classpath. A clean
stop/start afterwards launches first time, every time. This is Wings' behaviour with any
egg whose installer writes as root, not something this egg introduces.

Also worth knowing: the Forge egg in the upstream collection asks for
`ghcr.io/pelican-eggs/installers:java`, which is not a published tag — the real ones are
`java_8` through `java_25`. Ours uses `java_21`.

## The Version page

Picks flavour, version and build, then reinstalls. Variables go through the egg's own
rules (`VariableValidatorService`), and the variable writes, image change and reinstall
share one transaction, so a refused reinstall leaves nothing changed.

It also picks Java. The requirement is Mojang's own `javaVersion.majorVersion` for that
Minecraft version, mapped onto the lowest egg image at or above it. Switching is on by
default. Turning it off shows a warning, because the server will almost never start.

The content installer is in `CONTENT.md`.

## Folia, Quilt and Aikar's flags

Folia comes from the same Fill v3 API as Paper (`FoliaCatalogue` extends `PaperCatalogue`);
only Folia-declared plugins are offered for it. Quilt runs its installer on the node,
which writes `quilt-server-launch.jar`; the startup command launches that when present.

`AIKAR_FLAGS` (boolean) adds Aikar's G1 flags, with the >12 GB variant when
`SERVER_MEMORY` is above 12288. `G1RSetUpdatingPauseIntervalMillis` is left out on
purpose: Java 21 and 25 refuse to start with it. The set was checked on Java 8 to 25.

Existing servers keep their own copy of the startup command, so they pick the flags up
only once their startup is reset to the egg's.

## Players and Properties

`Players` changes lists through the console while the server runs (it owns the files
and would overwrite them), and edits the JSON directly when it is stopped, resolving
UUIDs from `usercache.json`, then Mojang, or the offline-mode UUID. Online players come
from the Server List Ping, so `hide-online-players` shows a count only. Heads are cut
from the Mojang skin by the panel (`/wyvern/heads/{name}`), never a third-party service.

`Properties` shows only keys the file has, so it fits every version. Saving writes only
changed keys and keeps everything else as it was. Known limit: a running server rewrites
the whole file when a command changes a property (for example `whitelist on`), which
drops edits saved since its last start. Restart after saving.

Both pages are gated by the custom subuser permissions `minecraft.players` and
`minecraft.properties`.

## Console fixes

`mc_port`, `mc_memory`, `mc_dependency`, `mc_client_mod` and `mc_world_version`, in
`src/Minecraft/Features`. Wings matches the line but does not pass it on, so the ones
that need details read the end of `logs/latest.log`.

Properties also has a filter, a dot on keys that differ from Mojang's default, a reset
per key, and a guard that asks before leaving with unsaved changes. The defaults live in
`PropertyCatalog::DEFAULTS`; keys without one show no marker.

## Worlds

A world is any root folder holding `level.dat`; Bukkit's `_nether` and `_the_end` fold
into their world. The page downloads a world as a `tar.gz` built by Wings (removed two
hours later by `DeleteFileLater`), switches `level-name`, deletes unused worlds, and
resets the active one with an optional seed and backup. A reset needs the server stopped,
and keeps `datapacks/`: it is parked in `.wyvern/`, the world deleted, then moved back, so
worldgen packs like Terralith shape the new world. Datapacks come from Modrinth's
`datapack` loader, matched to the server's Minecraft version.

Uploading goes through Files: an upload page would only duplicate it.

## Scheduled restarts

Two schedule tasks, registered on Pelican's `TaskService`:

- `wyvern_restart_countdown` takes a countdown in minutes as its payload and warns players
  at each of 30, 15, 10, 5 and 1 minutes and 30 and 10 seconds that fits in it, then
  restarts. Each step is a delayed `CountdownStep` job, so the schedule returns at once.
- `wyvern_restart_empty` restarts only when nobody is online (Server List Ping, or
  `dynamic.json` on FiveM).

Both skip a server that is not running.
