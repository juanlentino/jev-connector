# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project uses
0.x versioning until the public API is frozen at 1.0.

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
