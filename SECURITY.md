# Security

## Reporting a vulnerability

Use GitHub's private reporting form:
https://github.com/juanlentino/jev-connector/security/advisories/new.
Please do not open a public issue. You will get an acknowledgement within
three days and a fix or a reasoned decision within thirty. Credit is given in
the changelog unless you would rather it was not.

Once the plugin is listed in the WordPress directory, reports can also go
through the [WordPress plugin security process](https://developer.wordpress.org/plugins/wordpress-org/plugin-security/reporting-plugin-security-issues/).

## What this plugin does with data

It is a bring-your-own-key client. Everything below is also stated in the
`== External services ==` section of `readme.txt`, which is the disclosure the
directory reviewers hold the plugin to.

**Outbound.** The only endpoint is `https://api.typesafe.ai/v1/systemone`. A
request carries the state your code passes (which may be post or comment
content), the questions, the model id, your API key as a bearer token, and a
User-Agent naming the plugin, its version and the site's home URL. Nothing is
sent on activation, page load, or a schedule; only when your code calls the
client, when the REST proxy is called by a permitted user, or when a module you
switched on runs.

**The comment guardrail** sends the comment text, the display name the author
typed, and the title of the post being commented on. It does not send the
author's email address, URL, IP address or user agent. Filter
`jevc_guardrail_state` to narrow it further.

**The term-suggestions module** sends the post title and the post body with
HTML stripped.

**Stored.** The API key is stored by WordPress core under the Connectors API,
not by this plugin, and can be kept out of the database entirely with the
`TYPESAFE_API_KEY` constant or environment variable. Responses are cached in
transients keyed by a hash of the request for one hour. Guardrail decisions are
written to comment meta as `_jevc_decision`.

**Removed on uninstall.** The plugin option and the connector credential.
Transients expire on their own; comment meta is left with the comments.

## Trust boundaries

- The REST routes require a logged-in user with a capability: `edit_posts` by
  default for `/ask` and `/status` (changeable on the settings screen or with
  `jevc_rest_capability`), and `edit_post` for the specific post on the
  term-suggestion routes. The key never reaches the browser.
- Settings are written only through the Settings API, which supplies the nonce
  and capability check, and every value goes through a sanitizer.
- Question ids supplied by callers are escaped before they appear in error
  messages.
- The client makes no request until a key resolves, and follows no redirects.
- The guardrail's worst verdict is `spam`, which is recoverable. It never
  trashes a comment, and an API failure leaves the comment's status untouched.
