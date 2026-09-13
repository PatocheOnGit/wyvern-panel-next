# Integrating the redesign

Mockups: https://claude.ai/code/artifact/504fbf0b-22e3-4699-9f44-491182c7dea0

The direction settled on a cool infrastructure ground (#0B1017), borderless cards
carried by elevation rather than outlines, one teal light source, and illustrated
server cards. Archivo and Azeret Mono throughout.

## The rule that keeps this maintainable

**Nothing of ours edits an upstream file unless there is no seam.** So far the diff
against Pelican is:

| Upstream file | Change |
|---|---|
| `bootstrap/providers.php` | one line registering `Wyvern\WyvernServiceProvider` |
| `app/Providers/Filament/PanelProvider.php` | 11 lines: colours, font, dark default |
| `app/Providers/Filament/AppPanelProvider.php` | sidebar navigation items |
| `resources/views/livewire/server-entry*.blade.php` | the card design |

Everything else lives in `src/` and `resources/css/wyvern-theme.css`.

## Slices landed

### 1 — surface treatment

`src/WyvernTheme.php` carries the palette. The slate ramp is calibrated so Filament's
own usage lands on the mockup values: it reads `gray-950` for the body and `gray-900`
for raised chrome, so eleven hex values repaint most of the panel for free.

`resources/css/wyvern-theme.css` does what the palette cannot — shape. Filament
outlines every card with `ring-1 ring-white/10`; we want borderless with elevation.
This is the only file reaching for `!important`, because a ring utility composes into
`box-shadow` and replacing it means winning that property outright.

It loads through the `STYLES_AFTER` render hook, not Vite's emission order: Filament
serves its own panel stylesheet and Pelican injects `app.css` on `STYLES_BEFORE`, so
ours has to land after both. Verified in the rendered head.

### 2 — server cards

The client home is a Filament grid of `ServerEntryColumn`. That column is a **wrapper**
that resolves the record and mounts the `ServerEntry` Livewire component lazily — it is
not the card. The card is two views:

| View | When |
|---|---|
| `livewire/server-entry-placeholder.blade.php` | while the daemon is being asked |
| `livewire/server-entry.blade.php` | once it answers |

Both are built to the same shape so nothing jumps between them. Overwriting the wrapper
by mistake once took the whole page down with an undefined `$server`; the wrapper stays
upstream's.

**Cover art.** Real game key art is licensed material we cannot ship, and only 50 of 328
eggs carry an embedded icon. `Wyvern\ServerCover` generates the cover instead: a
gradient whose hue derives from the egg — stable per game, different between games —
with the accent and status hues reserved so no cover competes with what sits on it. An
egg icon rides it as an emblem where one exists; otherwise the server's initials do.

### 3 — shortcuts and sidebar

`Wyvern\Filament\Widgets\ShortcutsWidget` is the host's own link row above the cards. It
registers through `ListServers::registerCustomHeaderWidgets()`, the extension point
Pelican already provides, so no upstream page is touched.

Links come from `config/wyvern.php`. **An entry without a url is skipped**, so a fresh
install shows only what has been set up rather than a row of dead buttons. Set them with
`WYVERN_DISCORD_URL`, `WYVERN_STATUS_URL`, `WYVERN_STORE_URL`, `WYVERN_DOCS_URL`. Only
the documentation link has a default today, so only it appears.

The client panel had `->navigation(false)` — no sidebar at all, just a topbar — while
the server panel has one. That switch of navigation model mid-journey is what the
sidebar fixes. It carries the three destinations that actually exist: Servers, Profile,
and Admin for those who can reach it.

## Still to do

- The console: stat cards, the players column, the inline feature offer.
- Plugins and modpacks — a Modrinth integration, not a layout change.
- The dense server list with filters and node grouping.

## Checking work without a browser

The session running this cannot sign in, so visual confirmation is Ethan's. What can be
checked from here, and was:

```
php artisan view:cache          # every Blade compiles
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

plus rendering the views and the widget against the real server through tinker, which
catches undefined variables that compilation alone will not.
