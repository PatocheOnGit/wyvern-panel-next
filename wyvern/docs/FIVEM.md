# FiveM

The FiveM egg (`games-standalone/gta/fivem/egg-five-m.yaml`, PLCN_v3) and four server
pages in `src/Filament/Server/Pages/FiveM`. `FiveMServer::of()` recognises the egg and
exposes its variables; every page hides itself on other servers.

## The egg

- **Startup** passes `sv_maxclients` (the old `sv_maxplayers` is ignored by FXServer) and
  `onesync`, adds `sv_licenseKey` only when one is set, and skips `+exec server.cfg`
  under txAdmin, which runs the config itself.
- **Slots and OneSync** are variables: up to 2048 with OneSync `on`. Without it
  FXServer caps at 48, which the slots field on the Config page says.
- **Done** matches both `succeeded. Welcome!` and txAdmin's `All ready! Please access`,
  so a txAdmin server leaves "Starting".
- **Install** resolves a channel (`recommended`, `latest`) or an exact build from Cfx's
  changelog API, replaces `alpine/`, and copies `cfx-server-data` with `cp -n`, so a
  reinstall never overwrites edited resources. The default `server.cfg` is written by
  the script; `sessionmanager` and `hardcap` are gone from FXServer and no longer ensured.
  It writes `.wyvern/install.json` like the Minecraft egg.
- **txAdmin** listens on `TXHOST_TXA_PORT`, which is now a variable rather than a fixed
  40120, so it can point at an allocation the server actually has.

## Pages

| Page | What it does |
|---|---|
| Artifact | channel or exact build from `runtime.fivem.net`, then a reinstall; cached 30 min |
| Config | hostname, slots and OneSync through the variables; the rest written to `server.cfg` |
| Resources | lists folders under `resources/` (brackets included) and the built-in ones in `citizen/system_resources`; ensure or stop, live through the console when running |
| Players | `players.json` from the game port, with `clientkick` |

`ServerCfg` parses and rewrites `server.cfg` line by line, so comments and unknown lines
stay. Config writes `sets sv_projectName`, `tags`, `locale`, banners, `sv_enforceGameBuild`,
`sv_scriptHookAllowed`, `sv_pureLevel`, `sv_endpointprivacy`, `rcon_password` and
`mysql_connection_string`.

The **database** button creates a server database through Pelican's own service and
writes `mysql_connection_string`, which is what oxmysql (ESX and QBCore) reads. The host
is the database host's address, not its display name.

Players and Config are gated by the subuser permissions `fivem.players` and
`fivem.config`.

## txAdmin

A Console header action opens txAdmin in a new tab, and shows the first-run PIN, parsed
from the last 100 console lines (it is printed once, on the line after "Use the PIN
below to register"). When the txAdmin port is not reachable it offers the server's other
allocations and reinstalls with that port.

## Console fixes

`fivem_license` (no licence key), `fivem_port` (port already in use) and
`fivem_game_build` (unsupported game build), in `src/FiveM/Features`.

## Known limits

- The Players page is untested with real players: that needs a Cfx licence key.
- A database host declared as `127.0.0.1` is unreachable from game containers. Declare
  the node's address instead.
