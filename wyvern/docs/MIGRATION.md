# Migration to Pelican — done 2026-09-13

Decision and reasoning are in `BASE-EVALUATION.md` (kept in the old repo, and in the
artifact linked from memory). Ethan confirmed Wyvern stays private, so AGPL-3.0 costs
nothing and Pelican wins on every other axis.

## What now runs

| | |
|---|---|
| Panel | `~/wyvern/panel-next`, Pelican v1.0.0-beta38, Laravel 13.25, Filament 5.7 |
| Wings | `/usr/local/bin/pelican-wings`, v1.0.0-beta29 |
| Panel URL | `http://localhost:8000` (nginx `wyvern.conf`) |
| Wings config | `/etc/pelican/config.yml`, data root `/var/lib/pelican/volumes` |
| Units | `pelican-wings`, `pelicanq` (queue worker) |
| Cron | `* * * * * php ~/wyvern/panel-next/artisan schedule:run` |
| Database | `wyvern_panel` in the `wyvern-db` container |
| Node | id 1, `local`, fqdn `localhost`, http, :8080, sftp :2022 |
| Admin | `saintpatoche` / `254776717+PatocheOnGit@users.noreply.github.com` |

Panel and Wings handshake verified: the panel reads Wings 1.0.0-beta29 over
`/api/system`, Wings authenticates back and reports server states.

## The Wyvern layer

One commit, deliberately thin. Pelican's layout is untouched — only colour, type and
the mark.

- `src/WyvernTheme.php` — the palette in Filament's six colour roles. Slate for every
  surface, teal as the single accent, red/green/yellow for status. Filament emits
  these as OKLCH custom properties, so nothing fights the compiled stylesheet.
- `resources/css/wyvern.css` — Archivo and Azeret Mono self-hosted through
  `@fontsource`, plus the mono stack, which `font()` does not cover. Loaded once per
  panel through `font()` + `LocalFontProvider`. No CDN.
- `defaultThemeMode(ThemeMode::Dark)` — Wyvern is dark-first. The switcher stays.
- `public/wyvern/mark.svg` — the mark, read through `APP_LOGO` / `APP_FAVICON`.
  It lives outside `public/assets`, which Pelican gitignores.

All of it sits in the shared `PanelProvider`, so the admin, app and server panels
inherit it together. `vendor/bin/pint` and `phpstan` both pass.

## Kept on purpose

- Database `wyvern` — the old Pterodactyl data, 1.5 MB.
- `/var/lib/wyvern/volumes/66ccffc6-…` — the Paper world, 239 MB. Not reachable from
  the new panel; re-importing means creating a server and copying the files in.
- `PatocheOnGit/wyvern-panel` and `wyvern-wings` on GitHub — the Pterodactyl fork,
  private, fully pushed including all the React and AdminLTE work.

## Removed

`~/wyvern/panel`, `~/wyvern/wings`, `/usr/local/bin/wings`, `/etc/wyvern`,
`wings.service`, `wyvernq.service`, the dead yolks container, the `pterodactyl_nw`
network, and the `ghcr.io/pterodactyl/*` images. 1.7 GB reclaimed.

## Open

- `panel-next` has no `origin` remote yet. It tracks `upstream` =
  `pelican-dev/panel`. A new private GitHub repo is needed — not created, since repo
  creation needs Ethan's go-ahead.
- The footer reads "© 2026 Wyvern - Powered by Pelican", built by
  `FilamentServiceProvider` whenever `APP_NAME` is not "pelican". Ethan's standing
  rule is Wyvern everywhere; removing an upstream credit is his call, so it stays
  until he says otherwise.
- `p:info` reports timezone UTC despite `APP_TIMEZONE=Europe/Paris`; Pelican keeps
  some settings in the database. Worth a look when convenient.
- Layout work is deliberately not started. Next steps are Ethan's to pick: eggs and a
  first server, the Wyvern game modules as a Pelican plugin, or layout.
