# Working on this plugin

Context for future sessions. These are decisions with reasons, not preferences.
Several of them look like bugs or oversights if you do not know why they are
there, so read this before "fixing" any of them.

## What this is

A bring-your-own-key WordPress client for the TypeSafe System One API (Jev).
Core is a library. Everything opinionated is an optional module, off by default.
Nothing in the client may depend on a module existing.

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

**The guardrail never returns `trash`.** Its worst verdict is `spam`, which is
recoverable from the spam folder. The expensive failure in comment moderation is
discarding a real reader's comment, not missing spam.

**The guardrail requires two questions to agree.** A `noul` on spam and a
`choice` on routing. One confident misread must not be enough to act. Collapsing
these into a single question would be simpler and worse.

**Low confidence always holds.** Uncertainty is never read as permission. Every
path that is not an explicit confident verdict returns `0`.

**An API failure returns the comment's existing status untouched.** An outage
must never silently change how a site moderates. Do not "improve" this by
defaulting to hold on error.

**The tagger does not hook `save_post`.** Autosaves, revisions and REST writes
all fire it, so you would pay for three API calls to tag once. Suggestions are
on demand, and terms are written only by an explicit click.

**The tagger suggests from existing terms only.** It never invents new ones.
That keeps a taxonomy from sprawling and is a feature, not a limitation to fix.

**`Requires at least: 7.0`, with no fallback credential path.** Key management is
entirely the core Connectors API. A second, weaker key field for older versions
would mean two paths to keep in sync forever.

## Conventions

- Prefix everything `jevc_` / namespace `JevConnector`. Text domain
  `connector-for-typesafe-jev`.
- Files stay small and single-purpose. Decision logic lives in pure static
  methods (`Comment_Guardrail::decide()`, `Auto_Tagger::suggestions_from()`) so
  it can be tested without WordPress.
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
are covered. What has not been exercised by a human on WordPress 7.x: whether
the connector card renders under its own type rather than with the AI
providers, and whether the term suggestions panel behaves in the block editor.
Both were built by reading core source, not by testing.
