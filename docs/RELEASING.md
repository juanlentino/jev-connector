# Releasing

Releases are cut from `main`, which is protected: nothing lands there except
through a pull request with every CI check green. A release is a version-bump
PR followed by a tag.

## Versioning

Semantic versioning, 0.x until the public API (the `ask()` signature, the
`Question` and `Response` methods, the hook names and arguments) is frozen at
1.0.

| Bump | When |
| --- | --- |
| PATCH | Fixes, packaging, CI, docs that ship, performance, refactors |
| MINOR | A new user-visible capability: a module, a hook, a route, a setting |
| MAJOR (post-1.0) | Removed or renamed public API, a settings change without migration, or a behaviour change that needs user action |

Documentation-only changes to files that do not ship (`README.md`, `docs/`,
`CONTRIBUTING.md`) do not bump the version. They go under `## [Unreleased]`
in `CHANGELOG.md` and ride the next release.

## The version lives in three places

They must move together. The directory serves whatever `Stable tag` points at.

```
connector-for-typesafe-jev.php    * Version:           X.Y.Z
connector-for-typesafe-jev.php    const VERSION     = 'X.Y.Z';
readme.txt                        Stable tag: X.Y.Z
```

## Procedure

1. Branch from `main`. Bump the three version strings.
2. `CHANGELOG.md`: rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD` and
   start a fresh empty `## [Unreleased]` above it.
3. `readme.txt`: add a `= X.Y.Z =` entry under `== Changelog ==`. Shorter and
   user-facing; this is what the directory shows.
4. `readme.txt`: confirm `Tested up to` is the current WordPress release
   (`https://api.wordpress.org/core/version-check/1.7/`). Plugin Check fails
   if it is behind the current major.
5. If anything now sends data it did not before, `== External services ==`
   in `readme.txt` must already say so.
6. `composer check`. Open the PR, wait for green, merge.
7. Tag the merge commit and push the tag:

   ```bash
   git fetch origin main
   git tag -a vX.Y.Z origin/main -m "X.Y.Z: one line"
   git push origin vX.Y.Z
   ```

8. The Release workflow builds `connector-for-typesafe-jev.zip` from
   `.distignore` and attaches it to a GitHub release with generated notes.
   Download it and check the file list and the three version strings before
   doing anything else with it.

## What ships

`.distignore` is the single definition. Anything not listed there is in the
zip. The CI Plugin Check job stages the same file set, so a file that
should not ship will be flagged before the release, not after.

## Publishing to the directory

**First submission:** upload the zip at
https://wordpress.org/plugins/developers/add/. Review takes one to ten days
and cannot be expedited. Reply in the review email thread; do not resubmit.
The slug comes from the `Plugin Name:` header and is permanent.

**Every release after approval,** from the SVN checkout:

```bash
svn co https://plugins.svn.wordpress.org/connector-for-typesafe-jev/ svn-plugin
cd svn-plugin
rm -rf trunk/* && unzip -q ../connector-for-typesafe-jev.zip && mv connector-for-typesafe-jev/* trunk/ && rmdir connector-for-typesafe-jev
svn add --force trunk
svn cp trunk tags/X.Y.Z
svn ci -m "Release X.Y.Z"
```

Directory images (icon, banner, screenshots) go in the SVN `assets/`
directory, not the zip. Sizes are in `.wordpress-org/README.md`. Do not put
the TypeSafe mark on those; the directory treats third-party branding on
listing assets as an implied endorsement. The mark inside the plugin, on the
Connectors card, identifies the service and is a different matter.
