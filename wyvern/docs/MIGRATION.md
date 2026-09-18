# Migration to Pelican — done 2026-09-13

Decision and reasoning are in `BASE-EVALUATION.md`. Ethan confirmed Wyvern stays
private, so AGPL-3.0 costs nothing and Pelican wins on every other axis.

Note for future readers: this file says *Pelican* on purpose. The rebrand pass renamed
the running product to Wyvern, but the upstream project is still called Pelican and
pretending otherwise here would make the history unreadable.

## What runs

| | |
|---|---|
| Panel | `~/wyvern/panel-next`, upstream Pelican v1.0.0-beta38, Laravel 13.25, Filament 5.7 |
| Wings | `/usr/local/bin/wyvern-wings`, upstream v1.0.0-beta29 |
| Panel URL | `http://localhost:8000` (nginx `wyvern.conf`) |
| Wings config | `/etc/wyvern/config.yml`, data root `/var/lib/wyvern/volumes` |
| Units | `wyvern-wings`, `wyvernq` |
| Cron | `* * * * * php ~/wyvern/panel-next/artisan schedule:run` |
| Database | `wyvern_panel` in the `wyvern-db` container |
| Node | id 1, `local`, fqdn `localhost`, http :8080, sftp :2022 |
| System user | `wyvern` (uid 995), docker network `wyvern_nw` |

Wings needs `--config /etc/wyvern/config.yml` in its unit: the binary's built-in
default is `/etc/pelican/config.yml`.

## The Wyvern layer

Deliberately thin — 11 lines in `PanelProvider.php` plus our own files. Upstream's
layout is untouched.

- `src/WyvernTheme.php` — the palette in Filament's six colour roles. Slate for every
  surface, teal as the single accent, red/green/yellow for status. Filament emits them
  as OKLCH custom properties, so nothing fights the compiled stylesheet.
- `resources/css/wyvern.css` — Archivo and Azeret Mono self-hosted through
  `@fontsource`, plus the mono stack, which `font()` does not cover. One `<link>` per
  panel via `font()` + `LocalFontProvider`. No CDN.
- `defaultThemeMode(ThemeMode::Dark)`. The switcher stays.
- `public/wyvern/mark.svg`, read through `APP_LOGO` / `APP_FAVICON`. It lives outside
  `public/assets`, which upstream gitignores.

## The rebrand pass

89 files rewritten. Three things survive on purpose, because they are contracts rather
than branding:

| Survivor | Why |
|---|---|
| `Pelican Wings` in `DaemonRepository` | the User-Agent the daemon sends, matched by regex. Our Wings is a downloaded binary; renaming it needs a Go build. |
| `.pelicanignore` in `File.php` | a filename Wings actually reads. The wording around it was reworded; the filename was not. |
| `pelican-eggs` URLs and `ghcr.io/pelican-eggs/*` | a real GitHub org and a real registry namespace. The egg index depends on them. |

`SoftwareVersionService` still checks `pelican/panel` releases. That is how we learn a
new upstream release exists, and it surfaces as a version number, not a name.

Removed rather than repointed: the three "upload logs" actions, which posted log
contents to `logs.pelican.dev`. There is no Wyvern equivalent, and a private panel
should not ship a button that sends its logs to a third party.

Also deleted: upstream's community process files — funding, issue templates, the CLA
and its workflow, bounties, contributing and security. They describe Pelican's
project, not ours.

## First server

| | |
|---|---|
| Name | Paper 26.2 |
| Egg | Paper, imported from `pelican-eggs/minecraft` (no eggs ship seeded) |
| UUID | `13a038aa-1392-4439-b459-cf54e112ad11` |
| Version | Paper 26.2-123 on Minecraft 26.2, Java 25 |
| Address | `0.0.0.0:25565`, alias `localhost` |
| Limits | 4096 MB memory, 10240 MB disk, CPU unmetered |
| EULA | accepted — `eula.txt` written into the volume |

Allocation IP is `0.0.0.0` with alias `localhost`, the same shape the Pterodactyl
setup needed: `127.0.0.1` makes Wings publish the game port on the docker bridge
instead of somewhere Windows can reach.

### Power actions come from the panel, not from a bug

While the first server was being built, power events appeared that nothing in the
setup scripts had sent. The activity log settles it:

    id 15  12:48:42  server:power.stop   actor=<admin>  ip=::1
    id 14  12:43:51  server:file.read    actor=<admin>  file=eula.txt
    id 13  12:39:42  server:power.start  actor=<admin>

Ethan was in the panel in his own browser at the same time. The nginx access log shows
two Livewire sessions polling in parallel, his Chrome and the automation's.

The first server (`2dd3994a-…`) went further than a stop: its row, its volume and its
egg row all disappeared, and Wings destroyed its sinks. `servers.egg_id` is a foreign
key onto `eggs`, so removing an egg from the admin area takes its servers with it.
Egg and server deletion are not among the events Pelican writes to the activity log,
which is why nothing was recorded. Worth remembering before blaming the scheduler:
`p:egg:check-updates` only writes a cache flag, and `EggImporterService` updates an egg
in place through `Egg::where('uuid', …)->first() ?? new Egg()` — neither deletes.

## Kept on purpose

- Database `wyvern` — the old Pterodactyl data, 1.5 MB.
- `/var/lib/wyvern/volumes/66ccffc6-…` — the Paper world, 239 MB. It now sits inside
  the live data root, so re-importing is a rename onto a new server's UUID.
- `PatocheOnGit/wyvern-panel` and `wyvern-wings` on GitHub — the Pterodactyl fork,
  private, fully pushed including all the React and AdminLTE work.

## Open

- `panel-next` has no `origin` remote. It tracks `upstream` = `pelican-dev/panel`. A
  new private repo is needed; repo creation waits on Ethan.
- `pelican0` is the bridge device name Wings gives its docker network. Hardcoded in
  the binary — a Go build would be needed to change it.
- `p:info` reports the timezone as UTC despite `APP_TIMEZONE=Europe/Paris`; Pelican
  keeps some settings in the database.
