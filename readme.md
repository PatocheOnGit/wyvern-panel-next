<img width="18%" src="public/wyvern/mark.svg" alt="Wyvern">

# Wyvern

A game server control panel. Built for one person's own servers.

## What this is a fork of

Wyvern is a derivative work, and the parts it derives from matter more than the parts
it adds:

| | | |
|---|---|---|
| **Panel** | fork of [Pelican Panel](https://github.com/pelican-dev/panel) | **AGPL-3.0** — see `license` |
| **Wings** (the daemon) | [Pelican Wings](https://github.com/pelican-dev/wings), itself a fork of [Pterodactyl Wings](https://github.com/pterodactyl/wings) by Dane Everitt and contributors | MIT |
| **Eggs** (server definitions) | derived from [pelican-eggs](https://github.com/pelican-eggs) | MIT |

Wings is used **unmodified** — the installer downloads the official binary from Pelican's
releases and runs it under a Wyvern name. There is no Wyvern fork of it to maintain, and
none is needed.

The panel is AGPL-3.0, which is why this repository is public: the licence asks that
anyone offered the software over a network be offered its source too, and publishing is
the simplest way to mean it.

## Status

**Personal project, no support, no warranty.** It is pre-1.0 and the interface is
expected to change. You are welcome to read it, fork it, or install it, but nothing here
is promising to keep working for you.

If you want a game panel to actually rely on, install
[Pelican](https://pelican.dev) — it is what this is built on, it is maintained by a
team, and it has documentation.

## Versioning

Wyvern carries its own SemVer and records the Pelican release underneath it as data:

```
Wyvern 0.2.1  ·  Pelican v1.0.0-beta38
```

`wyvern/docs/VERSIONING.md` explains the scheme, and why the upstream version is
deliberately not baked into the number.

## Where things are

| | |
|---|---|
| Wyvern's own code | `src/` — namespace `Wyvern\` |
| Theme and design system | `src/WyvernTheme.php`, `resources/css/wyvern/` |
| Notes and decisions | `wyvern/docs/` — start with `REDESIGN.md` |
| Mark | `public/wyvern/mark.svg` |

Everything else is upstream and stays close to it, so rebasing on a new Pelican release
keeps costing minutes rather than days. New behaviour belongs in a plugin under
`plugins/`, not in a patch to `app/`.

## Installing

See [`wyvern-installer`](https://github.com/PatocheOnGit/wyvern-installer) — one script,
Debian and Ubuntu, panel + Wings + database + admin account.

## Upstream

Wyvern tracks `pelican-dev/panel`. The remote is `upstream`; releases arrive as tags.

```
git fetch upstream --tags
git rebase v1.0.0-betaNN
```

The version widget checks this repository's releases for Wyvern updates, and the
upstream check tells us when a new Pelican exists — which is how we learn to rebase.
