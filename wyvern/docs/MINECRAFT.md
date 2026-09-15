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

## Still to come

The picker UI, and the content installer for Modrinth **and** CurseForge. CurseForge
needs an API key, which Modrinth does not; both will sit behind one interface so a
server's loader decides which facets are searched and whether files land in `plugins/`
or `mods/`.
