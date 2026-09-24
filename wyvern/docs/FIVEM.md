# FiveM

The FiveM egg (`games-standalone/gta/fivem/egg-five-m.yaml`, PLCN_v3) and four server
pages in `src/Filament/Server/Pages/FiveM`. `FiveMServer::of()` recognises the egg and
exposes its variables; every page hides itself on other servers.

## Console commands, and why the server looked stuck

Two old bugs, both from Pterodactyl, had one root.

**Without txAdmin**, FXServer runs a line editor on its TTY (the `cfx>` prompt) and waits
for `\r`. Wings writes every command as `command\n`, so commands piled up in the prompt
and never ran.

**With txAdmin**, the game server is a child of txAdmin, and its stdin is a pipe
txAdmin owns. Wings writes to txAdmin's own FXServer, which ignores the line; txAdmin
has no stdin relay and no API that takes commands.

The stop command, `quit`, is a console command too. So a stop or a restart never
reached the server: Wings waited out its stop timeout, killed the container, took the
exit for a crash, and its crash handler raced the restart for the power lock. The
container came back up while Wings still called it offline, until a later sync. That
was the "stuck on Started resource fivem-map-hipster, then online at some point".
Silence after the last `Started resource` line is normal: FXServer prints nothing more
until someone joins.

The egg now starts through `.wyvern/start.sh`, written by the install:

- **Without txAdmin** the server reads a pipe fed by `cat`, so each line is a command.
  The script waits on the server itself, so the container ends when it quits.
- **With txAdmin** each console line is appended to `console.in` inside the
  `wyvern-console` resource, which the script copies into the artifact's
  `system_resources` and ensures, with an `add_ace`, at the end of txAdmin's
  `server.cfg`. The resource reads the file with `LoadResourceFile`, since FXServer's Lua
  `io` cannot reach absolute paths, and runs each line with `ExecuteCommand`. `quit`
  becomes a SIGINT to txAdmin, which stops the game server and exits; sent to the game
  server, txAdmin would just restart it.

Measured on the live node under txAdmin: start to running in 17 s, `restart chat`
from the panel console runs, restart and stop take 3 s, no crash handling.

FXServer segfaults while quitting, on both paths; the script sets `ulimit -c 0` so no core
file lands in the server folder.

## The egg

- **Platform**: `FIVEM_PLATFORM` is `legacy` (FXServer, FiveM and RedM) or `enhanced`
  (FiveM for GTA V Enhanced, the new `cfx-server`, in early access since July 2026).
- **Slots, name, keys**: passed with `+set` after `+exec server.cfg`, so the server's
  settings win over a stale line. Wings also keeps `sv_hostname` and `sv_maxclients` in
  `server.cfg` in step, written as `set name value`, which Enhanced needs.
- **OneSync** is a command-line convar on legacy only; set in `server.cfg` it is too late
  and FXServer warns that it is internal. Enhanced always runs OneSync.
- **Done** matches `succeeded. Welcome!`, txAdmin's `All ready! Please access`, and
  `regex:Found \d+ resources`, the only line Enhanced prints on the way up.
- **Stop** is `quit`, which the start script turns into a clean stop in both modes.
- **Install**: legacy resolves a channel or an exact build from Cfx's changelog API;
  Enhanced takes the one build Cfx publishes, read from the `__NEXT_DATA__` of
  `docs.fivem.net/docs/server-download`, which is the only place it is listed. Both replace
  `alpine/`, copy `cfx-server-data` with `cp -n`, and write `.wyvern/install.json` with
  the platform. Old egg lines are removed on reinstall: `set onesync`,
  `sv_endpointprivacy` (FXServer dropped it), and the empty licence and Steam lines.
- **txAdmin** gets `TXHOST_FXS_PORT` (the game binds to the primary allocation),
  `TXHOST_DEFAULT_CFXKEY` (the licence variable, offered in its setup) and
  `TXHOST_PROVIDER_NAME=Wyvern`.

## Enhanced, as found on build 155

- The archive is `cfx-server_linux_x64.tar.xz`: `alpine/opt/cfx-server/cfx-server`, the
  musl loader in `alpine/lib`, and `system_resources` next to the binary.
- txAdmin (9.0.0 beta) does not start on Linux: it prints `txAdmin says: *` and never
  listens. The start script runs Enhanced without it and says so.
- The console takes `set`, `restart`, `quit` and so on, but `status`, `players`,
  `cmdlist` and bare convar names are gone.
- It prints internal JSON (`SetPerfAuth`, `SetOneSyncAuth`) with tokens to the console.
- The default resources from `cfx-server-data` load as they are.

## Pages

| Page | What it does |
|---|---|
| Artifact | platform, then a legacy channel or exact build, or the current Enhanced build; a reinstall |
| Config | the `server.cfg` the server really reads; without txAdmin, hostname, slots and OneSync through the variables |
| Resources | the `resources/` next to that `server.cfg`, plus the artifact's `system_resources`; ensure or stop, live through the console |
| Players | `players.json` from the game port, with `clientkick` |

`Layout` finds the files: the server root, or under txAdmin the deployment named in
`txData/default/config.json` (`dataPath`, `cfgPath`). Before txAdmin's setup has run there
is no deployment, and the pages say so.

`ServerCfg` parses and rewrites `server.cfg` line by line, so comments and unknown lines
stay. Convars are written as `set name value` and read in either form. On Enhanced the
Config page hides OneSync, pure mode and ScriptHook, and offers only the latest game build
or 1.

The **database** button creates a server database through Pelican's own service and
writes `mysql_connection_string`, which is what oxmysql (ESX and QBCore) reads. The host
is the database host's address, not its display name.

Players and Config are gated by the subuser permissions `fivem.players` and
`fivem.config`.

## txAdmin

A Console header action opens txAdmin in a new tab, and shows the first-run PIN, parsed
from the last 100 console lines (it is printed once, on the line after "Use the PIN
below to register"). When the txAdmin port is not reachable it offers the server's other
allocations and reinstalls with that port. It is hidden on Enhanced.

## Console fixes

`fivem_license` (no licence key), `fivem_port` (port already in use) and
`fivem_game_build` (unsupported game build, including Enhanced's "invalid game build
enforcement"), in `src/FiveM/Features`.

## Known limits

- An existing server keeps its own startup command: set it to `bash .wyvern/start.sh`
  and reinstall to move it onto the start script.
- The Players page is untested with real players.
- Enhanced was booted in a container, not installed through the panel on a live server.
- A database host declared as `127.0.0.1` is unreachable from game containers. Declare
  the node's address instead.
