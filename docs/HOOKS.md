# Hooks

Every filter and action the plugin exposes, with the exact arguments. All
names are prefixed `jevc_`. Where a hook is fired is given so you can read the
surrounding code.

## Request lifecycle

Fired in this order for every call to `JevConnector\ask()` or `Client::ask()`.

### `jevc_default_model` (filter)

`( string $model ): string` — [class-client.php](../includes/class-client.php)

The model id used when the caller passes none. Default `jev-latest`. Also
what `GET /jev/v1/status` reports as `model`.

### `jevc_http_timeout` (filter)

`( int $seconds ): int` — default `20`.

### `jevc_request_payload` (filter)

`( array $payload, array $args ): array`

The body about to be JSON-encoded: `state`, `model`, `questions`. This is the
last chance to redact state. The cache key is computed *after* this filter,
so anything you change here changes the key.

### `jevc_cache_ttl` (filter)

`( int $ttl, array $payload ): int` — [class-cache.php](../includes/class-cache.php)

Seconds to keep a successful response. Default `HOUR_IN_SECONDS`. Return `0`
to disable caching. A per-call `array( 'cache' => false )` argument bypasses
the cache without touching the TTL.

### `jevc_request_args` (filter)

`( array $request_args, array $args ): array`

The array handed to `wp_remote_post()`: headers (including `Authorization`),
body, timeout, `redirection => 0`. Useful for a proxy or a test transport.

### `jevc_retry_delay` (filter)

`( int $seconds, int $attempt ): int`

Backoff before retry `$attempt + 1`. Default is `Retry-After` when the server
sent one (capped at 30), otherwise `min( 8, 2 ** ( $attempt - 1 ) )`. Return
`0` to skip sleeping; the test suite does.

### `jevc_after_response` (action)

`( JevConnector\Response $response, array $args )`

A call succeeded. Fires before the response is cached.

### `jevc_request_failed` (action)

`( WP_Error $error, array $args )`

A call failed for good, after retries. Error codes are listed in
[REST-API.md](REST-API.md#error-codes).

## REST

### `jevc_rest_capability` (filter)

`( string $capability ): string` — [class-rest-controller.php](../includes/class-rest-controller.php)

Capability checked by `POST /jev/v1/ask` and `GET /jev/v1/status`. Default
`edit_posts`.

## Connector registration

### `jevc_connector_type` (filter)

`( string $type ): string` — [class-connector.php](../includes/class-connector.php)

Groups the card on **Settings → Connectors**. Default `ai_decision`. Do not
return `ai_provider`; core would hand the key to the generative AI Client for
validation and clear it. Changing the type does not move the stored key,
because `setting_name`, `constant_name` and `env_var_name` are declared
explicitly.

### `jevc_connector_args` (filter)

`( array $args ): array`

The whole definition passed to `WP_Connector_Registry::register()`.
