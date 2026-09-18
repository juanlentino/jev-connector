# Connector for TypeSafe Jev

A WordPress connector for the [TypeSafe](https://docs.typesafe.ai) System One API. It gives your themes and plugins a typed way to ask questions about content and get back values you can branch on — a probability, a named choice, or a score — each with a confidence figure.

No prose parsing. No prompt wrangling in your template files.

## Why typed answers

Jev answers three kinds of question, and many of them in a single call:

| Primitive | Question | Answer |
| --- | --- | --- |
| `noul` | "Is this comment spam?" | `0.94` |
| `choice` | "Which category fits?" | `"essays"` plus a distribution and confidence |
| `score` | "How severe is this?" | `1.3` on a scale you defined, plus confidence |

## Install

**From a release zip**

Download the latest zip from [Releases](https://github.com/juanlentino/jev-connector/releases) and install it through **Plugins → Add New → Upload Plugin**.

**From source**

```bash
git clone https://github.com/juanlentino/jev-connector.git wp-content/plugins/connector-for-typesafe-jev
```

## Configure

Requires WordPress 7.0, because the key is managed by the core [Connectors API](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/) rather than by anything this plugin invented.

Go to **Settings → Connectors**, find the TypeSafe Jev card, and paste a key from the [TypeSafe console](https://console.typesafe.ai/settings/keys). Core stores it, masks it in the UI and in REST responses, and resolves it in this order:

1. `TYPESAFE_API_KEY` environment variable
2. `TYPESAFE_API_KEY` constant in `wp-config.php`
3. The stored option

Nothing is sent to TypeSafe until a key resolves, and then only when your own code makes a call.

### Not an AI provider, on purpose

The core AI Client is generative: text, image, speech, video. Jev is not. It returns a probability, a choice, or a score with a confidence figure, and routing that through `generate_text()` throws away everything worth having. Core also special-cases `type => 'ai_provider'` by validating those keys against the AI Client and clearing the ones it cannot verify, which would quietly wipe a working Jev key.

So this registers as a non-generative service connector, the way a spam filter does. You still get core's key management. You just don't get misfiled.

## Use

```php
use JevConnector\Question;

$answer = JevConnector\ask(
    array(
        'title' => get_the_title(),
        'body'  => wp_strip_all_tags( get_the_content() ),
    ),
    array(
        'is_technical' => Question::noul( 'Does this post assume programming knowledge?' ),
        'audience'     => Question::choice(
            'Who is this written for?',
            array(
                'practitioner' => 'People who will act on it this week',
                'executive'    => 'People who fund the work but will not do it',
                'general'      => 'Interested readers with no stake',
            )
        ),
        'evidence'     => Question::score(
            'How well is the central claim supported?',
            array(
                'Assertion with no support',
                'Anecdote or a single example',
                'Cited sources a reader can check',
            )
        ),
    )
);

if ( is_wp_error( $answer ) ) {
    error_log( $answer->get_error_message() );
    return;
}

$answer->noul( 'is_technical' );          // 0.91
$answer->choice( 'audience' );            // 'practitioner'
$answer->confidence( 'audience' );        // 0.74
$answer->probabilities( 'audience' );     // [ 'practitioner' => 0.74, ... ]
$answer->score( 'evidence' );             // 1.6
$answer->is_confident( 'audience', 0.9 ); // false — do not act automatically
$answer->usage();                         // [ 'input_tokens' => 412, ... ]
```

### Confidence gating

Gate on consequences, not on a single global threshold. Reads and suggestions can run at 0.5; anything that deletes, publishes, or emails should want 0.9 or a human.

```php
if ( $answer->is_confident( 'route', 0.9 ) ) {
    act( $answer->choice( 'route' ) );
} elseif ( $answer->is_confident( 'route', 0.6 ) ) {
    flag_for_review();
} else {
    leave_it_alone();
}
```

For a `noul`, `is_confident()` measures distance from 0.5, so 0.97 and 0.03 are both confident and 0.52 is not.

## REST

`POST /wp-json/jev/v1/ask` — same payload shape as the PHP helper. Requires a logged-in user with `edit_posts` by default.

```js
await apiFetch( {
    path: '/jev/v1/ask',
    method: 'POST',
    data: {
        state: { body: content },
        questions: {
            headline: { type: 'noul', instructions: 'Does the headline match the body?' },
        },
    },
} );
```

`GET /wp-json/jev/v1/status` reports whether the connector is ready.

## Modules

Core is a library. Everything opinionated is a module, off by default, switched on with a checkbox under **Settings → TypeSafe Jev**. Nothing in the client depends on a module existing.

### Comment guardrail

Filters `pre_comment_approved` and asks two independent questions about the same comment: a `noul` on whether it is spam, and a `choice` on how a moderator should handle it.

**Both have to agree before anything happens.** A comment is marked spam only when the choice says trash *and* the spam probability clears the threshold. It is approved only when the choice says approve *and* the spam probability is correspondingly low. Every other combination holds for moderation.

That matters because the expensive failure here is not missing spam, it is throwing away a real comment from a real reader. So:

- The worst verdict is `spam`, recoverable from the spam folder. It never returns `trash`.
- Low confidence always lands on hold. Uncertainty is never read as permission.
- A `WP_Error` from the API returns whatever status WordPress already decided. An outage must not silently change how a site moderates.
- Every decision is written to comment meta as `_jevc_decision` with probabilities, confidence and model, so you can tune thresholds against your own traffic rather than guessing.

```php
add_filter( 'jevc_guardrail_decision', function ( $decision, $response, $commentdata ) {
    // Hold everything on a post you are watching, whatever Jev thinks.
    return 42 === (int) $commentdata['comment_post_ID'] ? 0 : $decision;
}, 10, 3 );
```

Defaults: spam 0.95, approve 0.90, and logged-in users who can edit posts skip evaluation entirely.

### Term suggestions

Adds a panel to the post editor. Click **Suggest terms** and it fans out one `noul` per existing term in the taxonomy, all in a single call, then lists what clears your threshold with a probability each.

It suggests. It does not apply. Terms are written only when an editor ticks them and clicks Apply, through a REST route gated on `edit_post` for that specific post. Nothing hooks `save_post`, because autosaves, revisions and REST writes all fire it and you would pay three times to tag once.

It draws from terms that already exist and never invents new ones, which keeps a taxonomy from sprawling. Capped at 40 candidates per call.

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `jevc_default_model` | filter | Change the model identifier |
| `jevc_http_timeout` | filter | Request timeout in seconds (default 20) |
| `jevc_request_payload` | filter | Last look at the body before encoding |
| `jevc_request_args` | filter | Arguments handed to `wp_remote_post()` |
| `jevc_retry_delay` | filter | Backoff in seconds; return 0 to disable sleeping |
| `jevc_rest_capability` | filter | Capability required by the REST route |
| `jevc_cache_ttl` | filter | Response cache lifetime; return 0 to disable |
| `jevc_connector_type` | filter | Connector type used to group the card |
| `jevc_connector_args` | filter | The whole connector definition before registration |
| `jevc_modules` | filter | The list of module classes |
| `jevc_guardrail_decision` | filter | The guardrail's verdict before it is applied |
| `jevc_guardrail_state` | filter | What gets sent about a comment |
| `jevc_after_response` | action | Fires with the `Response` on success |
| `jevc_request_failed` | action | Fires with the `WP_Error` on final failure |
| `jevc_guardrail_unavailable` | action | Fires when the guardrail could not reach TypeSafe |

## Caching

Identical requests are served from a transient keyed by a hash of the exact payload, so re-evaluating the same draft or comment costs nothing. Any change to the state, the questions, or the model is a miss. Failures are never cached.

```php
add_filter( 'jevc_cache_ttl', fn() => 0 );              // disable globally
JevConnector\ask( $state, $questions, [ 'cache' => false ] ); // bypass once
```

## Errors

Everything returns `WP_Error` rather than throwing. Codes: `jevc_not_configured`, `jevc_invalid_question`, `jevc_invalid_response`, `jevc_encode_failed`, and `jevc_http_<status>`. 429, 529 and 5xx are retried three times with backoff before giving up.

## Development

```bash
composer install
composer test   # PHPUnit
composer lint   # WordPress Coding Standards
```

## License

GPL-2.0-or-later. TypeSafe and Jev are trademarks of their owner; this plugin is an independent client and is not affiliated with or endorsed by TypeSafe. The TypeSafe mark in `assets/images/` is used only to identify the service on the core Connectors card, the way every connector card identifies its service, and will be removed on request.
