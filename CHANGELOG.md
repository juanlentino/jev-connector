# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project uses
0.x versioning until the public API is frozen at 1.0.

## [Unreleased]

### Added

- Repository documentation, none of which ships in the plugin zip:
  `CONTRIBUTING.md`, `SECURITY.md`, `docs/ARCHITECTURE.md`, `docs/HOOKS.md`
  (every filter and action with its exact arguments), `docs/REST-API.md`
  (every route and error code), `docs/MODULES.md` (the module contract and
  the guardrail's decision matrix), `docs/RELEASING.md` (version bump, tag,
  zip, SVN). README gained badges, a requirements section and an index.
- GitHub issue forms, a pull request template, and weekly grouped Dependabot
  for Composer dev tooling and workflow actions.
- Private vulnerability reporting enabled on the repository; `SECURITY.md`
  points there rather than at an email address.
- `composer check` runs lint and tests together.
- `tests/test-consumer-contract.php` pins the surface other plugins depend
  on, by the same `function_exists()` / `class_exists()` lookups a consumer
  uses: `JevConnector\ask()` and its `Response|WP_Error` return,
  `Response::to_array()` as the untouched body, `WP_Error` data carrying
  `status`, `Connector::get_api_key()` and `Connector::SETTING_NAME`. Signal &
  Noise Tools 16.5.3 is the first consumer. Changing anything in that file is
  a breaking change.

### Fixed

- The test bootstrap never loaded `includes/functions.php`, so
  `JevConnector\ask()` — the entry point consumers call — had no coverage.

## [0.2.2] - 2026-09-18

### Added

- `logo_url` on the connector registration, pointing at
  `assets/images/typesafe.png` (TypeSafe's published favicon mark, 192px,
  transparent). The card under **Settings → Connectors** showed core's generic
  plug icon before. `plugins_url()` is used the same way core resolves its own
  provider logos.

### Verified live

First run on a real WordPress 7.1 site, which retires two items from the
"still unverified" list: the card renders under its own type, separate from
the AI providers, and saving a key shows **Connected** rather than being wiped
by AI Client validation.

## [0.2.1] - 2026-09-18

Directory readiness. No behaviour change.

### Fixed

- Caller-supplied question ids are escaped before they are interpolated into
  validation messages (`WordPress.Security.EscapeOutput.ExceptionNotEscaped`,
  flagged by both PHPCS and Plugin Check).
- Removed the `Domain Path` header. It pointed at a `languages/` folder that
  does not exist, which Plugin Check flags, and wordpress.org serves language
  packs without it.
- `Tested up to` raised to 7.1, the current WordPress release. Plugin Check
  rejects a value behind the current major.
- The External services section of `readme.txt` now discloses that the
  User-Agent header carries the plugin version and the site's home URL, which
  the client has always sent.
- Renamed a `$default` parameter in `Module::setting()`; `default` is a
  reserved word and WPCS warns on it.

### Changed

- `.distignore` is now the single source of truth for what ships. The Release
  workflow builds the zip from it, and CI stages the same set into a folder
  named `connector-for-typesafe-jev` before running Plugin Check. Previously
  Plugin Check scanned the raw checkout, derived the slug `jev-connector` from
  the repo folder, and reported 58 false text-domain mismatches plus findings
  in test and tooling files that never ship. CI had been red on every push.
- PHPCS: the WordPress ruleset no longer applies to `tests/`, whose bootstrap
  must redeclare core function names as stubs. PHPCompatibility still does.
  `minimum_wp_version` now matches the plugin header. Array properties use the
  `<element>` syntax PHPCS 4 requires.
- CI: every job has a `timeout-minutes`; the PHPUnit matrix adds 8.5.

## [0.2.0] - 2026-09-18

### Added

- Module system: `Modules` registry plus a `Module` base class. Every module is
  off by default and switched on with a checkbox under
  **Settings → TypeSafe Jev**. Nothing in the client depends on a module
  existing.
- **Comment guardrail** module. Filters `pre_comment_approved` and asks two
  independent questions, a `noul` on spam and a `choice` on routing. Both must
  agree at or above the threshold before a comment is marked spam, and both
  must agree before one skips moderation. Everything else is held. It never
  returns `trash`, and a `WP_Error` from the API returns the comment's existing
  status untouched. Each decision is written to comment meta as
  `_jevc_decision` with probabilities, confidence and model, so thresholds can
  be tuned against real traffic.
- **Term suggestions** module. A post editor panel that fans out one `noul` per
  existing term in a single call and lists what clears the threshold, ordered
  by probability. Terms are applied only by an explicit click, through a
  capability-checked REST route. Nothing hooks `save_post`.
- Routes `POST /jev/v1/suggest-terms` and `POST /jev/v1/apply-terms`, both
  gated on `edit_post` for the specific post.

## [0.1.0] - 2026-09-18

### Added

- Registration with the WordPress 7.0 Connectors API, so core owns the
  credential: `Settings → Connectors` UI, masking in the admin and in REST
  responses, and resolution order of `TYPESAFE_API_KEY` environment variable,
  then constant, then stored option.
- Response cache keyed by a hash of the request payload, with a
  `jevc_cache_ttl` filter and a per-call `cache` argument. Failures are never
  cached.
- `Client` for `POST https://api.typesafe.ai/v1/systemone`, with bearer auth,
  a 20 second default timeout, and bounded exponential backoff on 429, 529 and
  5xx responses (honoring `Retry-After`).
- `Question::noul()`, `Question::choice()`, `Question::score()` builders with
  shape validation before anything leaves the site.
- `Response` reader: `noul()`, `choice()`, `score()`, `confidence()`,
  `probabilities()`, `legend()`, `usage()`, and `is_confident()`.
- Settings screen under **Settings → TypeSafe Jev** for the default model and
  the REST capability, with a connection status notice that names the key's
  source and links to the Connectors screen.
- REST routes `POST /jev/v1/ask` and `GET /jev/v1/status`, capability gated.
- Filters `jevc_default_model`, `jevc_http_timeout`, `jevc_request_payload`,
  `jevc_request_args`, `jevc_retry_delay`, `jevc_rest_capability`; actions
  `jevc_after_response`, `jevc_request_failed`.
- PHPUnit suite covering the request contract, error mapping, and retries.
