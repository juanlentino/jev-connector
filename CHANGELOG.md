# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project uses
0.x versioning until the public API is frozen at 1.0.

## [0.1.0] - 2026-09-18

### Added

- `Client` for `POST https://api.typesafe.ai/v1/systemone`, with bearer auth,
  a 20 second default timeout, and bounded exponential backoff on 429, 529 and
  5xx responses (honoring `Retry-After`).
- `Question::noul()`, `Question::choice()`, `Question::score()` builders with
  shape validation before anything leaves the site.
- `Response` reader: `noul()`, `choice()`, `score()`, `confidence()`,
  `probabilities()`, `legend()`, `usage()`, and `is_confident()`.
- Settings screen under **Settings → TypeSafe Jev** with an explicit outbound
  request consent checkbox and a `JEVC_API_KEY` constant override.
- REST routes `POST /jev/v1/ask` and `GET /jev/v1/status`, capability gated.
- Filters `jevc_default_model`, `jevc_http_timeout`, `jevc_request_payload`,
  `jevc_request_args`, `jevc_retry_delay`, `jevc_rest_capability`; actions
  `jevc_after_response`, `jevc_request_failed`.
- PHPUnit suite covering the request contract, error mapping, and retries.
