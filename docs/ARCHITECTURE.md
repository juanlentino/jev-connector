# Architecture

One request path and nothing else. The plugin has no screens: like the core
AI provider connectors, its UI is the card core draws on Settings →
Connectors, and every choice is a filter.

## Files

```
connector-for-typesafe-jev.php   Header, constants, requires, plugins_loaded bootstrap
includes/
  class-connector.php            Core Connectors API registration; key resolution
  class-client.php               HTTP: payload, cache check, retries, error mapping
  class-question.php             noul / choice / score builders and validation
  class-response.php             Typed reader over the decoded body
  class-cache.php                Transient cache keyed by payload hash
  class-rest-controller.php      /jev/v1/ask and /jev/v1/status
  class-exception.php            Thrown only for programmer error
  functions.php                  JevConnector\ask(), JevConnector\is_ready()
assets/images/typesafe.png       Card logo
uninstall.php                    Deletes the option and the credential
```

## A call, end to end

```
JevConnector\ask( $state, $questions, $args )        functions.php
  └ Client::ask()
      ├ no key?                → WP_Error jevc_not_configured, no request
      ├ Question::validate_map → WP_Error jevc_invalid_question
      ├ model: $args['model'] or filter jevc_default_model
      ├ build payload          → filter jevc_request_payload
      ├ Cache::get( payload )  → hit returns Response here
      ├ Client::send()
      │   ├ filter jevc_request_args
      │   ├ wp_remote_post, up to 3 attempts
      │   │   retry on WP_Error transport, 429, 5xx, 529; sleep via jevc_retry_delay
      │   ├ 200 + JSON → Response, action jevc_after_response
      │   └ otherwise  → WP_Error jevc_http_<status>, action jevc_request_failed
      └ Cache::set() on success only
```

The REST proxy calls the same `ask()` and returns `Response::to_array()`;
error codes map to HTTP statuses in [REST-API.md](REST-API.md).

## Credentials

The plugin owns no key field. `Connector::register()` hooks
`wp_connectors_init` and registers the card with core's
`WP_Connector_Registry`. Core renders the card, stores the key, masks it in
REST, and this plugin reads it back with the same precedence core uses:
`TYPESAFE_API_KEY` env var, then constant, then the option
`connectors_typesafe_api_key`.

It registers with `type => 'ai_decision'`, not `ai_provider`. Core hands
`ai_provider` keys to the PHP AI Client for validation on save and clears the
ones that fail, and the AI Client only understands generative capabilities.
A working Jev key would be wiped. This is the single most important line in
the codebase; [CLAUDE.md](../CLAUDE.md) explains it again.

## Choices without a screen

| Choice | Default | How to change it |
| --- | --- | --- |
| Model | `jev-latest` | `jevc_default_model` filter, or `model` per call |
| REST capability | `edit_posts` | `jevc_rest_capability` filter |
| Cache lifetime | one hour | `jevc_cache_ttl` filter, or `cache => false` per call |
| HTTP timeout | 20 s | `jevc_http_timeout` filter |

The plugin stores nothing of its own. The key is core's; the cache is
transients. `uninstall.php` still deletes `jevc_settings`, the option the
0.2.x settings screen wrote, so an upgraded site is left clean.

## Error convention

Runtime failure returns `WP_Error`, always, so calling code can follow
WordPress conventions. `JevConnector\Exception` is thrown only by the
`Question` builders when a request is being built wrongly (too few choice
options, out-of-range score levels), and `Client::ask()` converts even that
into `jevc_invalid_question` so nothing escapes to the caller as an
exception.

## Testing without WordPress

`tests/bootstrap.php` defines the ~25 WordPress functions the plugin calls,
backed by a `JevTestState` holder for queued HTTP responses, options and
transients. That is why the bootstrap is excluded from the WordPress
coding-standards ruleset: it has to redeclare core function names.
