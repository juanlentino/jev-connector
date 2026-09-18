# Modules

Core is a library. Everything opinionated is a module: off by default,
switched on by a checkbox under **Settings → TypeSafe Jev**, and never a
dependency of the client. Two ship with the plugin; you can add your own.

## The contract

Extend `JevConnector\Modules\Module` ([abstract-module.php](../includes/modules/abstract-module.php))
and implement four static methods plus `register()`:

```php
use JevConnector\Modules\Module;

final class Excerpt_Grader extends Module {
    public static function id(): string          { return 'excerpt_grader'; }
    public static function label(): string       { return __( 'Excerpt grader', 'my-plugin' ); }
    public static function description(): string { return __( 'Scores excerpts on publish.', 'my-plugin' ); }

    public static function defaults(): array { return array( 'threshold' => 0.8 ); }

    public static function sanitize( array $input ): array {
        return array( 'threshold' => max( 0.0, min( 1.0, (float) ( $input['threshold'] ?? 0.8 ) ) ) );
    }

    public function register(): void {
        // Only called when the module is enabled. Attach hooks here.
    }

    public function render_fields(): void {
        // Optional. Extra fields under the checkbox; use $this->field_name( 'threshold' ).
    }
}

add_filter( 'jevc_modules', fn( array $classes ) => array_merge( $classes, array( Excerpt_Grader::class ) ) );
```

- `id()` is the settings key. Stable; changing it orphans saved settings.
- `defaults()` and `sanitize()` are static so settings can be cleaned without
  instantiating anything. `sanitize()` receives only your module's branch of
  the submitted form and returns only your fields; the registry adds the
  `enabled` flag.
- Inside instance methods, `$this->setting( 'threshold', 0.8 )` reads a value
  merged over your defaults.
- Everything is stored under one option, `jevc_settings['modules'][ id ]`.

## Comment guardrail

[class-comment-guardrail.php](../includes/modules/class-comment-guardrail.php)

Filters `pre_comment_approved` at priority 20 and asks two independent
questions about the same comment in one call:

| Id | Type | Question |
| --- | --- | --- |
| `spam` | noul | Is this spam: unsolicited promotion, link farming, machine filler? |
| `route` | choice | approve / hold / trash — how should a moderator handle it? |

The decision matrix, from `decide()`, which is pure and unit-tested:

| `route` | `confidence` | `spam` | Result |
| --- | --- | --- | --- |
| `trash` | ≥ spam threshold | ≥ spam threshold | `'spam'` |
| `approve` | ≥ approve threshold | ≤ 1 − approve threshold | `1` (approve) |
| anything else | | | `0` (hold) |

Defaults: spam threshold 0.95, approve threshold 0.90. Both questions must
agree before either outcome; one confident misread is never enough to act.

Things it will not do, by design (see [CLAUDE.md](../CLAUDE.md)):

- Return `trash`. Spam is recoverable from the spam folder; trash is not.
- Change a comment that is already `spam`, `trash`, or a `WP_Error`.
- Evaluate comments from logged-in users who can `edit_posts` (default;
  switch off with **Skip evaluation for logged-in users**).
- Change anything when the API fails. The status WordPress already chose is
  returned, and `jevc_guardrail_unavailable` fires.

Every evaluated comment gets a `_jevc_decision` meta entry:
`decision`, `previous`, `spam`, `route`, `confidence`, `probabilities`,
`model`. Query it to tune thresholds against real traffic:

```php
// Comments you approved by hand that the guardrail had held: how sure was it?
foreach ( get_comments( array( 'status' => 'approve', 'meta_key' => '_jevc_decision' ) ) as $comment ) {
    $d = get_comment_meta( $comment->comment_ID, '_jevc_decision', true );
    if ( 0 === $d['decision'] ) {
        printf( "#%d route=%s conf=%.2f spam=%.2f\n", $comment->comment_ID, $d['route'], $d['confidence'], $d['spam'] );
    }
}
```

If most of those show `route=approve` with confidence just under 0.90, the
approve threshold is too high for your traffic.

## Term suggestions

[class-auto-tagger.php](../includes/modules/class-auto-tagger.php)

Adds a side panel on the post editor for the configured post types (default
`post`). Clicking **Suggest terms** posts to `/jev/v1/suggest-terms`, which
asks one `noul` per existing term in the taxonomy (default `post_tag`, up to
40 terms ordered by use) in a single call, and lists those at or above the
threshold (default 0.70) with their probability. Ticking terms and clicking
**Add selected** posts to `/jev/v1/apply-terms`.

- It only ever suggests existing terms. It never creates one.
- It never hooks `save_post`. Autosaves, revisions and REST writes all fire
  it; you would pay three calls to tag once.
- Both routes check `edit_post` for the specific post.

Settings: taxonomy, threshold. `post_types` is stored but has no field yet;
set it through the option if you need the panel on another type.

## Writing state for a module

Keep what you send narrow. The guardrail sends the comment, the typed author
name and the post title, and nothing else. If your module sends anything new,
add it to `== External services ==` in `readme.txt` in the same commit; that
section is what the directory holds the plugin to.
