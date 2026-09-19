=== Connector for TypeSafe Jev ===
Contributors: juanlentino
Tags: ai, classification, api, moderation, automation
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask typed questions about your content and get structured, confidence-scored answers from the TypeSafe System One API.

== Description ==

Most AI plugins hand you a paragraph of prose and leave you to parse it. This one does the opposite. It connects WordPress to the TypeSafe System One API, whose Jev model answers **typed** questions and returns values your code can branch on directly:

* **Noul** — the probability, from 0 to 1, that a statement is true.
* **Choice** — one option from a set you define, with the full probability distribution and a confidence figure.
* **Score** — a position on an ordered scale you define, with probabilities per level and a confidence figure.

Many questions can be sent in a single call and are evaluated in parallel.

At its core this is a connector, not a feature. The plumbing comes first:

* Registration with the WordPress Connectors API, so your key is managed by core under **Settings → Connectors** and never by a field this plugin invented.
* A PHP client with retry and backoff on rate-limit and overload responses.
* Question builders that validate the shape before anything leaves your site.
* A response reader with one accessor per answer type, plus a confidence gate.
* Response caching keyed by request content, so re-evaluating identical material costs nothing.
* A REST proxy at `/wp-json/jev/v1/ask` so admin-side JavaScript never sees your key.
* Filters and actions at every point worth intercepting.

= Optional modules =

**Comment guardrail.** Routes incoming comments to approve, hold, or spam. Two independent questions have to agree before anything is called spam, the worst verdict it will issue is recoverable from the spam folder, and it never returns trash. Anything it is unsure about is held for a human. Every decision is written to comment meta with its probabilities, so you can tune the thresholds against your own traffic instead of guessing. If TypeSafe is unreachable, the comment keeps whatever status WordPress already gave it.

**Term suggestions.** Adds a panel to the post editor that asks one question per existing term, all in a single call, and lists the ones that clear your threshold with a probability each. It suggests only. Nothing is written until you tick a term and click Apply, and it never hooks `save_post`.

= Example =

`
$answer = JevConnector\ask(
    array( 'comment' => $comment_text ),
    array(
        'spam'  => JevConnector\Question::noul( 'Is this comment spam?' ),
        'route' => JevConnector\Question::choice(
            'Where should this go?',
            array(
                'approve' => 'Clearly a real, on-topic comment',
                'hold'    => 'Plausible but needs a human look',
                'trash'   => 'Spam, abuse, or link farming',
            )
        ),
    )
);

if ( ! is_wp_error( $answer ) && $answer->is_confident( 'route', 0.9 ) ) {
    do_something( $answer->choice( 'route' ) );
}
`

== External services ==

This plugin connects to the TypeSafe System One API, a third-party service operated by TypeSafe, to evaluate the content you pass to it. You supply your own API key.

**What is sent:** the state you supply to `JevConnector\ask()` or to the REST route (which may include post content, comment text, or any other data your code passes), the questions you define, and your model identifier. Your API key is sent as a bearer token, and the request's User-Agent header identifies this plugin, its version, and your site's home URL.

**When it is sent:** only when your own code calls the client or the REST route, or when a module you have switched on runs. The comment guardrail sends a comment's text when that comment is submitted; the term suggestions panel sends a post's title and body when an editor clicks Suggest terms. Both are off by default. Nothing is sent until an administrator has connected TypeSafe under **Settings → Connectors**, and no request is made on activation, on page load, or on any schedule. Identical repeat requests are served from a local cache rather than re-sent.

**Where it goes:** `https://api.typesafe.ai/v1/systemone`

Service terms and policies: https://docs.typesafe.ai/legal
Documentation: https://docs.typesafe.ai

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to **Settings → Connectors** and add your TypeSafe API key to the TypeSafe Jev card. Keys are available from the TypeSafe console.
3. Optionally visit **Settings → TypeSafe Jev** to change the default model or the capability required by the REST route.

To keep the key out of the database, set it in the environment or in `wp-config.php` instead. WordPress checks both before the stored value:

`define( 'TYPESAFE_API_KEY', 'your-key' );`

== Frequently Asked Questions ==

= Do I need a TypeSafe account? =

Yes. The plugin is a bring-your-own-key client for a third-party API. Create a key at https://console.typesafe.ai/settings/keys.

= Why does this require WordPress 7.0? =

Because key management is handled entirely by the Connectors API introduced in 7.0. Rather than ship a second, weaker credential field for older versions, the plugin targets the platform that does it properly.

= Is this an AI provider for the core AI Client? =

No, and deliberately so. The core AI Client covers generative capabilities: text, image, speech, video. Jev is not generative. It returns a probability, a choice, or a score with a confidence figure, none of which survive a generative interface. The plugin registers as a non-generative service connector, the same way a spam filter does.

= Will the comment guardrail delete anything? =

No. The worst it does is mark a comment as spam, which you can undo from the spam folder, and it only does that when two separate questions agree at high confidence. Everything else is held for moderation. It never returns trash, and an API outage leaves moderation exactly as WordPress decided.

= Does anything get sent without my say-so? =

No. Until a key is present, every call returns a `jevc_not_configured` error and no HTTP request is made.

= Can I use this from the block editor? =

Yes. Call `POST /wp-json/jev/v1/ask` with a `state` and a `questions` object. The route requires a logged-in user with `edit_posts` by default; change the capability on the settings screen or with the `jevc_rest_capability` filter.

= What happens when TypeSafe is rate-limiting or overloaded? =

429, 529 and 5xx responses are retried up to three times with exponential backoff, honoring `Retry-After` when present. Everything else fails fast and returns a `WP_Error`.

= Which hooks are available? =

Filters: `jevc_default_model`, `jevc_http_timeout`, `jevc_request_payload`, `jevc_request_args`, `jevc_retry_delay`, `jevc_rest_capability`, `jevc_cache_ttl`, `jevc_connector_type`, `jevc_connector_args`, `jevc_modules`, `jevc_guardrail_decision`, `jevc_guardrail_state`. Actions: `jevc_after_response`, `jevc_request_failed`, `jevc_guardrail_unavailable`.

== Changelog ==

= 0.2.3 =
* Fixed the link to Settings → Connectors on the plugin's settings screen and in the not-connected notice. It pointed at a page core refuses; it now opens the Connectors screen.

= 0.2.2 =
* The connector card under Settings → Connectors now shows the TypeSafe mark instead of the generic placeholder.

= 0.2.1 =
* Directory readiness: escaped caller-supplied ids in validation messages, removed the unused Domain Path header, and marked the plugin tested up to WordPress 7.1.
* The External services disclosure now mentions the site URL sent in the User-Agent header.

= 0.2.0 =
* Added an optional comment guardrail module: two-question agreement, hold as the fallback, decisions logged to comment meta.
* Added an optional term suggestions module: one question per existing term in a single call, suggest-only.
* Both modules are off by default and can be switched on under Settings → TypeSafe Jev.

= 0.1.0 =
* First release: Connectors API registration, client, question builders, response reader, response cache, and REST proxy.
