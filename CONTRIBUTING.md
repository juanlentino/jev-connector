# Contributing

Thanks for looking. This is a small, opinionated codebase, and the fastest way
to get a change in is to read [CLAUDE.md](CLAUDE.md) first: it lists the design
decisions that look like bugs if you do not know why they are there, and a pull
request that reverses one of them will be closed with a pointer to that file.

## Setup

Requires PHP 7.4+ and Composer. No WordPress install is needed for the tests.

```bash
git clone https://github.com/juanlentino/jev-connector.git
cd jev-connector
composer install
composer check      # lint + tests
```

- `composer test` runs PHPUnit against `tests/`. The bootstrap stubs the
  handful of WordPress functions the plugin calls, so the suite runs in
  milliseconds with no database.
- `composer lint` runs PHPCS with the WordPress Coding Standards and
  PHPCompatibility for PHP 7.4+. `composer lint:fix` applies the automatic
  fixes.

To exercise the plugin for real, symlink or clone the repo into
`wp-content/plugins/connector-for-typesafe-jev` on a WordPress 7.0+ site.
The folder name matters: core derives text-domain and asset paths from it.

## What CI runs

Every pull request runs three jobs, all of which are required to merge:

| Job | What it checks |
| --- | --- |
| PHPUnit (7.4 → 8.5) | The test suite on every supported PHP version |
| WordPress Coding Standards | `composer lint` |
| WordPress Plugin Check | The same [Plugin Check](https://wordpress.org/plugins/plugin-check/) the directory reviewers run, on the exact file set that ships |

Plugin Check runs against a staged copy built from `.distignore`, in a folder
named after the slug. If you add a development file to the repo root, add it to
`.distignore` too or Plugin Check will flag it.

`main` is protected: pull requests only, all checks green, branch up to date.
This applies to the maintainer as well.

## Making a change

1. Branch from `main`.
2. Write the test first where the change has logic in it. Decision logic lives
   in pure static methods (`Comment_Guardrail::decide()`,
   `Auto_Tagger::suggestions_from()`, `Question::validate_map()`) precisely so
   it can be tested without WordPress.
3. Keep files small and single-purpose. Prefix everything `jevc_` or put it in
   the `JevConnector` namespace.
4. Return `WP_Error` for anything that can fail at runtime. Throw
   `JevConnector\Exception` only for programmer error in building a request.
5. If the change sends anything anywhere it did not before, update the
   `== External services ==` section of `readme.txt` in the same commit.
6. Add a line under `## [Unreleased]` in `CHANGELOG.md`.
7. Open a pull request. Explain the why, not just the what.

Do not bump the version in a feature PR. Releases are cut separately; see
[docs/RELEASING.md](docs/RELEASING.md).

## Adding a module

Modules are the opinionated layer and are off by default. See
[docs/MODULES.md](docs/MODULES.md) for the contract. The core client must never
depend on a module existing.

## Reporting a security issue

See [SECURITY.md](SECURITY.md). Please do not open a public issue for it.
