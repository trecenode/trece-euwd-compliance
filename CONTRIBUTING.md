# Contributing & maintaining

End-user documentation lives in [`README.md`](README.md). This file covers the dev workflow: building assets, versioning, and cutting a release.

## Requirements

- Node.js (for the asset build and release tooling — `esbuild` is the only runtime dep).
- `zip` and `git` on `PATH` (release packaging).
- `gh` CLI — only needed for the final "publish" step if you want to attach the zip to a GitHub release.

```bash
npm install
```

## Asset build

Source CSS/JS lives in `assets/css/*.css` and `assets/js/*.js`. Minified `*.min.*` siblings are committed because the plugin ships as a folder users drop into `wp-content/plugins/` — there's no build-then-package step at install time.

```bash
npm run build     # one-shot minify of all CSS + JS
npm run watch     # rebuilds on change (CSS and JS in parallel)
```

The runtime picks minified vs. source based on `SCRIPT_DEBUG` (see `Trece_WDEU_Plugin::maybe_enqueue_*` in `includes/class-plugin.php`). If you edit a source file and don't rebuild, the installed copy will keep serving the stale minified version.

## Versioning

**The plugin header's `Version:` line in `trece-withdrawal-eu.php` is the single source of truth at runtime.** `TRECE_WDEU_VERSION` (used as the asset cache-buster) is derived from it at bootstrap via `get_file_data()` — do not reintroduce a hardcoded define.

To bump just the version (without cutting a release):

```bash
npm run version:set 1.4.0
```

This rewrites the plugin header *and* the `README.md` badge atomically, validates semver, and is idempotent. `CHANGELOG.md` is the authoritative record of what was released and is edited by hand.

## Cutting a release

```bash
# 1. Write the new ## X.Y.Z section in CHANGELOG.md and commit it.

# 2. Cut the release locally.
npm run release 1.4.0
#    Bumps the version, runs the asset build, commits "1.4.0",
#    tags 1.4.0, and produces dist/trece-withdrawal-eu-1.4.0.zip
#    ready to attach to a GitHub release.

# 3. Publish (the release script never pushes on its own).
git push --follow-tags
gh release create 1.4.0 dist/trece-withdrawal-eu-1.4.0.zip \
  --title '1.4.0' \
  --notes-file <(awk '/^## 1\.4\.0/{flag=1;next}/^## /{flag=0}flag' CHANGELOG.md)
```

`npm run release` refuses to run if:

- the working tree is dirty,
- the tag already exists, or
- `CHANGELOG.md` has no matching `## X.Y.Z` section.

### What's in the zip

The zip is built from `git archive` of the freshly created tag (not the working tree), so it only contains what was actually committed. After extraction, dev-only meta is pruned:

| Pruned | Why |
|--------|-----|
| `scripts/` | Release tooling, not used at runtime |
| `package.json` | npm metadata, not a WP concern |
| `.distignore`, `.gitignore` | Repo plumbing |
| `doc/` | Internal docs, never shipped |

Both minified and unminified assets are included so `SCRIPT_DEBUG` keeps working on the installed copy.

### If a release goes wrong before publish

Everything `npm run release` does is local — nothing has been pushed. To unwind:

```bash
git tag -d 1.4.0           # delete the local tag
git reset --hard HEAD~1    # drop the release commit (only if it was just made)
rm -rf dist/
```

Then fix the underlying issue and re-run.
