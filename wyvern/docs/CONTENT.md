# Installing mods, plugins and modpacks

Two libraries behind one interface. A server's loader decides what is searched and where
files land; nothing asks the user to know that a Paper server wants `plugins/` and a
NeoForge one wants `mods/`.

## The shape

| Piece | Job |
|---|---|
| `ContentSource` | one library: search, then list a project's files |
| `ModrinthSource` | no key needed; identifies itself in the User-Agent as their docs ask |
| `CurseForgeSource` | needs an approved key; hides itself without one |
| `ServerProfile` | works out a server's loader and Minecraft version |
| `ContentInstaller` | resolves the target directory and pulls through Wings |

The download happens **on the node**. Wings' `files/pull` fetches the url directly, so a
60 MiB mod never travels through the panel and an install is fast enough to be a click.

`php artisan wyvern:content:check <server> --query=… --type=mod --install` searches as
that server would and can install the first result. That is how this was verified.

## CurseForge needs a key you have to ask for

Modrinth is open. CurseForge answers 403 to everything unauthenticated, and their docs
say a third-party modding service must **apply** for a key — "any registered account
which is not a game developer and did not request a key will be deleted". So:

- Set `WYVERN_CURSEFORGE_KEY` once you have one; the source turns itself on.
- Until then the picker shows Modrinth only, rather than a tab that returns nothing.

`CurseForgeSource` was written from their published schema, not from an observed
response, because no key was available to make one. Treat the first real call as the
thing that confirms it.

One CurseForge behaviour to design around: an author can forbid third-party
distribution, and the API then returns the file with `downloadUrl` null. `ContentFile`
carries a nullable url so the UI can explain why a download is not offered instead of
failing at install time.

## Two things that bit during the build

**Modrinth's search index and its project endpoint disagree.** `/project/chunky` reports
`project_type: mod`, but searching with `project_type:mod` **and** a Bukkit-family
loader returns nothing at all — the index files those under `plugin`. Faceting on the
value the project endpoint reports is the wrong guess, and it fails as an empty result
rather than an error, which is the kind of bug that ships.

**`Server::variables()` returns nulls when eager loaded.** The relation builds
`server_value` from a join filtered on `$this->id`, which is not bound during eager
loading. `ServerProfile` therefore queries the tables itself rather than trusting how
the caller loaded the server — otherwise a `with('variables')` upstream silently makes
every server look like it has no loader.

## The known gap: unpinned versions

A server installed with `MC_VERSION=latest` never records what "latest" turned out to
be, so nothing can narrow its mod list to one Minecraft version. Left alone, that
installs the wrong build: the first JEI release Modrinth returned for the test server
was a 1.21.1 backport, which would not load on 26.2.

For now the command prints each file's game versions and warns when a server pins
nothing, so the mismatch is visible. The proper fix is for the install script to record
what it resolved — loader, version, build — into a file the panel can read back. That is
worth doing before the picker ships.

## Not done yet

**Modpacks.** A Modrinth `.mrpack` is not a server: it is a zip holding
`modrinth.index.json`, a list of files to fetch, and an `overrides/` tree to lay on top.
"Click install and it runs" therefore means download, unpack, fetch every listed file,
apply the overrides — a queued job, not one pull. CurseForge modpacks are the same shape
with a different manifest. That is the next piece.

## What was actually run

Chunky installed onto the Paper server from Modrinth, landing in `plugins/`. JEI
installed onto the NeoForge server, landing in `mods/`; NeoForge listed it at boot
(`Just Enough Items 30.32.0.221`) and the server reached `Done (0.429s)!` with the port
listening.
