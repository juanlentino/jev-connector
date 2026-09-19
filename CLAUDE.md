# Working on this plugin

Context for future sessions. These are decisions with reasons, not preferences.
Several of them look like bugs or oversights if you do not know why they are
there, so read this before "fixing" any of them.

## What this is

A bring-your-own-key WordPress client for the TypeSafe System One API (Jev).
It is a pure connector, like the core AI provider connectors for Anthropic and
Google: the card on Settings → Connectors, a PHP client, a REST proxy, hooks.
No menu, no screens, no features. 0.2.x had a settings screen and two
opinionated modules (comment guardrail, term suggestions); 0.3.0 removed them
because what a site does with an answer belongs to the plugin that owns the
content, and the only consumer (Signal & Noise Tools) never used them.

## Decisions that must not be quietly reversed

**Do not register as `type => 'ai_provider'`.** This looks like the obvious fix
and it is wrong. Core hands `ai_provider` keys to the PHP AI Client and
validates them against it on save, clearing keys it cannot verify. The AI Client
covers generative capabilities only (text, image, speech, video). Jev returns
probabilities, choices and scores. Registering as an AI provider would get a
working key silently wiped. Akismet's non-AI connector is the precedent we
follow. See `includes/class-connector.php`.

**The connector `type` is not a user setting.** It groups the card and, for AI
providers, feeds the auto-generated `setting_name`. A user flipping it after
saving a key orphans the credential. It is filterable for developers
(`jevc_connector_type`) and that is safe only because `setting_name`,
`constant_name` and `env_var_name` are all declared explicitly.

**No settings screen, no modules.** Model and REST capability are filters
(`jevc_default_model`, `jevc_rest_capability`), the way the provider
connectors leave choices to code. A screen would exist only to hold features,
and features that decide what to do with an answer belong in the consumer.
The 0.2.x modules are in git history under tag `v0.2.5` if a separate plugin
ever wants them.

**`Requires at least: 7.0`, with no fallback credential path.** Key management is
entirely the core Connectors API. A second, weaker key field for older versions
would mean two paths to keep in sync forever.

## Conventions

- Prefix everything `jevc_` / namespace `JevConnector`. Text domain
  `connector-for-typesafe-jev`.
- Files stay small and single-purpose. Logic lives in pure static methods
  (`Question::validate_map()`, the `Response` readers) so it can be tested
  without WordPress.
- `CHANGELOG.md` is updated with every change. 0.x versioning until the public
  API is frozen at 1.0.
- Everything returns `WP_Error` rather than throwing. `Exception` is only for
  programmer error in building a request.

## Before shipping

- `composer test` and `composer lint` must pass.
- CI runs Plugin Check against a real WordPress install, on the staged file
  set in a folder named after the slug. **It must stay that way.** Pointing it
  at the raw checkout makes it derive the slug from the repo folder name
  (`jev-connector`) and report every `__()` call as a text-domain mismatch.
  Unescaped output, unsanitized input and missing nonces are the directory's
  top three rejection reasons.
- `.distignore` defines what ships. Adding a dev file to the root means adding
  it there too, or Plugin Check will flag it.
- `readme.txt` has an `== External services ==` section. If you add a code path
  that sends anything anywhere, update it in the same commit.
- Bump `Version:` in the plugin header, `const VERSION`, and `Stable tag:` in
  `readme.txt` together. The directory serves whatever `Stable tag` points at.
- `Tested up to` must be the current WordPress release or Plugin Check fails.

## Still unverified

Plugin Check runs in CI against a live install, so static and readme checks
are covered. Seen working on a real WordPress 7.1 site (2026-09-18): the
connector card renders under its own type, separate from the AI providers,
and saving a key shows Connected rather than being wiped by AI Client
validation. Signal & Noise Tools 16.5.3 runs every Jev request through
`JevConnector\ask()` on that site (118 requests, 0 failures in one session).
