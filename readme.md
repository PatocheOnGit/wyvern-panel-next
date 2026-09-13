<img width="18%" src="public/wyvern/mark.svg" alt="Wyvern">

# Wyvern

A game server control panel. Private, and built to stay that way.

Wyvern is a fork of [Pelican Panel](https://github.com/pelican-dev/panel), which is
itself a modern alternative in the Pterodactyl ecosystem. The upstream project is
AGPL-3.0; so is this. See `license`.

## Where things are

| | |
|---|---|
| Wyvern's own code | `src/` — namespace `Wyvern\` |
| Theme | `src/WyvernTheme.php`, `resources/css/wyvern.css` |
| Mark | `public/wyvern/mark.svg` |
| Notes and decisions | `wyvern/docs/` |

Everything else is upstream and should stay close to it, so rebasing on a new Pelican
release keeps costing minutes rather than days. New behaviour belongs in a plugin
under `plugins/`, not in a patch to `app/`.

## Running it locally

The panel is served by nginx and php-fpm; Wings runs as `wyvern-wings`. Redis and
MariaDB live in containers. `wyvern/docs/MIGRATION.md` has the full layout — paths,
units, ports and the gotchas worth knowing before touching any of it.

```
php artisan p:info          # what the panel thinks it is running
systemctl status wyvern-wings
```

## Upstream

Wyvern tracks `pelican-dev/panel`. The remote is `upstream`; releases arrive as tags.

```
git fetch upstream --tags
git rebase v1.0.0-betaNN
```

The version widget in the admin area still checks upstream's releases — that is
deliberate, it is how we learn a new Pelican release exists.
