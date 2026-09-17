# The redesign

Branch `feat/redesign`, started 2026-09-16. Supersedes the *Instrumentation v2*
direction entirely — navy-slate surfaces, a teal accent, Archivo and Azeret Mono are
gone, along with the dark-only stylesheet that made them work.

## The one idea

**Colour means a state, never a decoration.** The only urgent question a game panel
answers is whether a server is running, so saturation is spent on that and nothing else:
the ground carries the identity, colour carries the information.

Everything else follows from it. The accent is cold because every warm accent collides
with a status — a brand amber and a warning amber are indistinguishable in an 8px dot.
Server cover art lost its per-game gradient because three hundred hues competed with the
status on the same card. The file toolbar went neutral because none of its six buttons is
a state. A full quota stopped being red because red means an error.

## The system

| | |
|---|---|
| Palette | `src/WyvernTheme.php` — SAND (every surface, both themes), AZURE (the one accent), GREEN/AMBER/RED (state only) |
| Tokens | `resources/css/wyvern/tokens.css` — branched `:root` / `:root.dark`. **Nothing below this file writes a hex.** |
| Type | Instrument Sans + JetBrains Mono, variable, self-hosted. Sans is language, mono is values — numbers, addresses, paths, identifiers, console. Mono is never a label. |
| Radius | Retuned globally by shadowing Tailwind's own `--radius-*`, which reaches every `rounded-*` in Filament's compiled stylesheet |
| Density | `--wy-row-h`, with a `[data-wy-density]` hook for a future preference |

`resources/css/wyvern-theme.css` is now a manifest importing four parts from
`resources/css/wyvern/`; they are excluded from Vite's entry glob in `vite.config.js` so
the panel still loads one stylesheet.

**Hairlines came back, and they cost no `!important`.** Filament outlines cards with a
Tailwind ring, and a ring reads `--tw-ring-color` — so the colour can be set rather than
the composed `box-shadow` overridden. The sixteen `!important` rules the old file needed
are gone from the chrome.

## What shipped

1. **Foundations** — two-theme tokens, typography, density, focus rings. Light mode works;
   it never did before.
2. **Shell** — the topbar is unconditional now. It was tied to the nav-type preference, so
   the default sidebar mode had no topbar at all and therefore nowhere to anchor search,
   breadcrumbs or context. Five nav groups on the server panel, with the three
   `navigationSort = 11` collisions resolved. A bottom tab bar on phones. Breadcrumbs back
   in admin.
3. **Actions** — create actions keep their label, and thirteen toolbar actions got theirs
   back. See the trap below; it is the least obvious thing in here.
4. **Client home** — the server card rebuilt around which server and is it up. Meters dash
   rather than sit at zero when there is no ceiling to measure against.
5. **Console** — the terminal follows the theme, and the stat blocks carry a ratio so they
   can draw meters. Copyable blocks are buttons now, so they are reachable by keyboard.
6. **Files** — one dropdown per row instead of four controls, one of them a red trash can
   beside the dropdown trigger.
7. **Admin dashboard** — fleet tiles, node health, recent activity. The donation and help
   panels are gone. The update check points at this repository and admits when it could
   not check.
8. **Command palette** — Cmd or Ctrl and K, on every panel.

## Traps, all of them found by running the panel rather than reading it

- **The icon-only interface was never a preference.** `FilamentServiceProvider` applied
  `hiddenLabel()` unconditionally to Create/Edit/View/Delete/Associate, and a *generic*
  `Action::configureUsing` turns every action into an icon button when
  `CustomizationKey::ButtonStyle` is set — which is the default. That generic callback runs
  **after** the per-type ones, so fixing the create action's own config alone does nothing.
  The exclusion list in that block is the seam; create actions are excluded by type.
  For the record `ButtonStyle` offers only `icon` and `icon_button`, so a labelled button
  was never one of its options and none of this overrides a user's choice.
- **`cache:clear` signs everyone out.** `CACHE_STORE` and `SESSION_DRIVER` are both redis
  and both resolve to the `default` connection, i.e. db 0, which Laravel's redis cache
  flush empties with `flushdb()`. Use `config:clear`, `route:clear`, `view:clear`.
- **Error pages are not panel pages.** No panel render hook fires on them, so `app.css`
  (STYLES_BEFORE) and `wyvern-theme.css` (STYLES_AFTER) never loaded, and
  `filament()->getTheme()` had no panel to resolve. A 404 linked exactly one stylesheet,
  the font one. `errors/layout.blade.php` loads both explicitly now.
- **xterm will not composite.** `transparent` is not a colour its parser knows — it falls
  back to black — and `rgba(0,0,0,0)` is painted opaque black by the WebGL renderer. It
  gets a real colour from `--wy-console-bg`. The sixteen ANSI colours are deliberately not
  tokenised, because a server writing an error in red has to look red, but they have a
  second set for light mode.
- **A lazy widget renders as an empty card.** That was the client home's empty box, not an
  empty-content bug.
- **`Filament\Tables\Table` has no `limit()`.** Cap in the query; `paginated(false)` calls
  `get()` on it anyway.
- **`WyvernPlugin` is registered per panel.** It was on the server panel only, so an admin
  branch inside it silently never ran.
- **Three navigation shapes** — sidebar, topbar, mixed — are a user preference and all three
  have to survive. `RoleResource` even changes nav group depending on it.
- **SPA is on everywhere except the console**, so new JS must re-initialise on
  `livewire:navigated`.

## Also shipped

9.  **Files** — right-click a row for its menu; one dropdown per row; delete labelled and
    red again.
10. **Power controls on every server page**, through `CanCustomizeHeaderActions`. A second
    implementation on purpose: `ListServers::getPowerActionGroup()` dispatches a
    `powerAction` event whose only listener is on the app panel, so reused elsewhere its
    buttons would appear, be clickable, and do nothing.
11. **Pinned servers**, in the existing customization JSON, keyed by UUID so a stale pin
    matches no row and needs no cleanup.
12. **Keyboard** — `?` for the sheet, `g`+letter to jump, per panel.
13. **Density** — Compact/Comfortable, emitted as two token values server-side.
14. **The API docs index**, on the token system instead of hex and emoji.
15. **Egg and server deletion are logged**, via model events so it holds however the
    deletion was triggered.

## Naming: two things are already called "shortcuts"

The host link row above the server cards owns both `wyvern.shortcuts.*` in the lang file
and `.wy-shortcuts` in CSS. The keyboard feature is `wyvern.keyboard.*` and `.wy-keys`
because of it — a duplicate array key silently drops one block, and the duplicate class
leaked a `max-width` onto that row. Check both namespaces before adding a third.

## Still open

- **No Filament view has been published.** `resources/views/vendor/` still holds only
  scramble's. Every change so far went through a render hook, a Filament API, or a Pelican
  Blade file — so there is no `OVERRIDES.md` to keep yet, and it is worth keeping it that
  way as long as possible.
- The palette offers pages and servers, not actions: power actions need the Filament action
  infrastructure, which a bespoke Alpine component does not have.
- Skeletons beyond the server card placeholder.
- The console remains outside SPA mode, so entering it is a full reload.
- `public/wyvern/loaders/vanilla.svg` is 152 KB and `forge.png` 151 KB.
- `SmallStatBlock` carries a ratio now, but the three console charts still hard-code
  `rgba(96,165,250,0.3)` for their fill.
- The client panel has no bottom tab bar by choice (three destinations); revisit if it
  gains more.

## Verification

Always on the running panel at `http://localhost:8000`, never a static HTML file: three
widths (1512 / 768 / 375), both themes, and the three navigation preferences. Then
`php artisan view:cache`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
