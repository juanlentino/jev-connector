=== Connector for TypeSafe Jev ===
Contributors: juanlentino
Tags: ai, classification, api, moderation, automation
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ask typed questions about your content and get structured, confidence-scored answers from the TypeSafe System One API.

== Description ==

Most AI plugins hand you a paragraph of prose and leave you to parse it. This one does the opposite. It connects WordPress to the TypeSafe System One API, whose Jev model answers **typed** questions and returns values your code can branch on directly:

* **Noul** — the probability, from 0 to 1, that a statement is true.
* **Choice** — one option from a set you define, with the full probability distribution and a confidence figure.
* **Score** — a position on an ordered scale you define, with probabilities per level and a confidence figure.

Many questions can be sent in a single call and are evaluated in parallel.

This plugin is a connector, not a feature. It ships no comment filter, no auto-tagger, no block editor panel. It gives you the plumbing to build those:

* A settings screen for your API key, with an explicit switch for outbound requests.
* A PHP client with retry and backoff on rate-limit and overload responses.
* Question builders that validate the shape before anything leaves your site.
* A response reader with one accessor per answer type, plus a confidence gate.
* A REST proxy at `/wp-json/jev/v1/ask` so admin-side JavaScript never sees your key.
* Filters and actions at every point worth intercepting.

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

This plugin connects to the TypeSafe System One API, a third-party service operated by TypeSafe, to evaluate the content you pass to it.

**What is sent:** the state you supply to `JevConnector\ask()` or to the REST route (which may include post content, comment text, or any other data your code passes), the questions you define, and your model identifier. Your API key is sent as a bearer token.

**When it is sent:** only when your code calls the client or the REST route, and only after a site administrator has saved an API key and checked the box allowing outbound requests. No request is made on activation, on page load, or on any schedule.

**Where it goes:** `https://api.typesafe.ai/v1/systemone`

Service terms and policies: https://docs.typesafe.ai/legal
Documentation: https://docs.typesafe.ai

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to **Settings → TypeSafe Jev**.
3. Paste an API key from the TypeSafe console, tick **Allow this site to contact api.typesafe.ai**, and save.

To keep the key out of the database, define it in `wp-config.php` instead:

`define( 'JEVC_API_KEY', 'your-key' );`

A defined constant always wins over the stored value and implies consent to make requests.

== Frequently Asked Questions ==

= Do I need a TypeSafe account? =

Yes. The plugin is a client for a paid third-party API. Create a key at https://console.typesafe.ai/settings/keys.

= Does anything get sent without my say-so? =

No. Until an API key is present and outbound requests are switched on, every call returns a `jevc_not_configured` error and no HTTP request is made.

= Can I use this from the block editor? =

Yes. Call `POST /wp-json/jev/v1/ask` with a `state` and a `questions` object. The route requires a logged-in user with `edit_posts` by default; change the capability on the settings screen or with the `jevc_rest_capability` filter.

= What happens when TypeSafe is rate-limiting or overloaded? =

429, 529 and 5xx responses are retried up to three times with exponential backoff, honoring `Retry-After` when present. Everything else fails fast and returns a `WP_Error`.

= Which hooks are available? =

`jevc_default_model`, `jevc_http_timeout`, `jevc_request_payload`, `jevc_request_args`, `jevc_retry_delay`, `jevc_rest_capability`, and the actions `jevc_after_response` and `jevc_request_failed`.

== Changelog ==

= 0.1.0 =
* First release: client, question builders, response reader, settings screen, and REST proxy.
