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
client or when the REST proxy is called by a permitted user. The plugin itself
never initiates a request.

**Stored.** The API key is stored by WordPress core under the Connectors API,
not by this plugin, and can be kept out of the database entirely with the
`TYPESAFE_API_KEY` constant or environment variable. Responses are cached in
transients keyed by a hash of the request for one hour.

**Removed on uninstall.** The connector credential, and the `jevc_settings`
option left by 0.2.x. Transients expire on their own.

## Trust boundaries

- The REST routes require a logged-in user with a capability, `edit_posts`
  by default (`jevc_rest_capability`). The key never reaches the browser.
- The plugin has no settings screen and no forms, so there is no input of its
  own to sanitize beyond the REST parameters, which are validated.
- Question ids supplied by callers are escaped before they appear in error
  messages.
- The client makes no request until a key resolves, and follows no redirects.
