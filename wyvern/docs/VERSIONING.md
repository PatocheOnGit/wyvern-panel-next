# Versioning

Wyvern carries **its own SemVer**, and records the Pelican release it is built on as
data rather than encoding it in the number.

```
Wyvern 0.2.1  ·  Pelican v1.0.0-beta38
```

| | |
|---|---|
| Tags | `v0.1.0`, `v0.2.0`, `v0.2.1` … |
| Where the number ends up | `config/app.php` `'version'`, stamped by `.github/workflows/release.yaml` |
| An untagged checkout | reports `canary (<sha>)`, which is not a version and is not treated as one |
| The Pelican base | `config/wyvern.php` `upstream.version`, updated when you rebase |

## Why 0.x

It is private, it is dev/beta only, and nothing about it promises a stable interface
yet. `0.x` says that without a paragraph. The first release anyone else could rely on
is `1.0.0`, and until then a minor bump is allowed to break things.

## Why the Pelican version is not in the number

The tempting scheme is `1.0.0-beta38-wyvern.1` — upstream base plus our revision. It is
wrong for three concrete reasons:

1. **`version_compare()` would rank an upstream bump above our own work.**
   `1.0.0-beta39-wyvern.1` sorts above `1.0.0-beta38-wyvern.7`, so a rebase would look
   like six Wyvern releases being undone. The panel uses `version_compare` to decide
   whether an update exists, so this is not theoretical.
2. **It makes our cadence hostage to theirs.** Every rebase would force a new version
   number even when nothing of ours changed, and a Wyvern fix could not ship without
   declaring an upstream base it had not been tested against.
3. **SemVer build metadata cannot order.** `1.0.0-beta38+wyvern.1` is the "correct"
   SemVer spelling, and the spec explicitly excludes build metadata from precedence —
   so it sorts as equal to plain `1.0.0-beta38` and is useless for update checks.

Recording the base as data costs one line in a config file and loses nothing: both
numbers are visible wherever the version is shown, and each moves for its own reason.

## Releasing

Tag and push; `release.yaml` does the rest.

```
git tag v0.1.0 && git push origin v0.1.0
```

It builds with `composer install --no-dev` and `yarn build`, creates a `release/v0.1.0`
branch, rewrites `'version' => 'canary'` to `'version' => '0.1.0'` on it, and attaches
`panel.tar.gz` + `checksum.txt` to a **draft** release. The draft is deliberate — you
publish it once you have looked at it.

Two things to know about that workflow:

- It strips exactly `refs/tags/v` from the ref, so a tag **must** start with `v` or the
  version lands mangled.
- `0.x` tags contain no `rc`/`beta`/`alpha`, so upstream's condition would mark them as
  stable releases. Ours marks any `v0.` tag as a prerelease too, which is what a 0.x
  private panel actually is.

## The trap that comes with marking 0.x as a prerelease

**`GET /releases/latest` excludes prereleases.** With only `v0.1.0` published it answers
404, so anything asking that question concludes there is no release at all — the
dashboard reported "could not check", and an installer would have found nothing to
download.

Both consumers therefore read `GET /releases?per_page=1` instead, which is ordered newest
first and includes prereleases. That is also the more honest question: *what is the most
recent release of this repository*, rather than *what is the most recent release GitHub
considers stable*.

Worth remembering the other GitHub limit while you are here: anonymous API requests are
capped at 60 per hour per IP. A VPS install makes a handful, so it is fine, but a loop
that polls will be refused — and a refusal is a 403 whose body looks nothing like a
release, which is exactly how an earlier version of this document came to claim that
upstream published no releases at all.

## What the installer consumes

`wyvern-installer` fetches the latest release's `panel.tar.gz` and verifies it against
`checksum.txt`. It does not clone: a tarball of a built tree needs no git, no composer
and no yarn on the VPS, which is most of what makes an install slow and fragile.
