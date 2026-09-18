# Submitting to the WordPress Plugin Directory

Everything in this repo is already shaped for the directory. What is left is the
part only you can do.

## Before you submit

- [ ] **Run Plugin Check locally.** Install the [Plugin Check](https://wordpress.org/plugins/plugin-check/)
      plugin on a test site, run it against this plugin, and clear every error.
      Warnings are worth reading but rarely block approval. CI runs the same
      check on every push.
- [ ] **Confirm `Tested up to`** in `readme.txt` matches the WordPress version
      you actually tested against. Reviewers check this.
- [ ] **Test the external-service path.** Connect TypeSafe under Settings →
      Connectors, make one call, then remove the key and confirm nothing goes
      out. The "External services" section of `readme.txt` is the single most
      common reason an API-backed plugin gets held in review, so make sure it
      describes exactly what your build sends. Connecting the service in core's
      own UI is the implied consent guideline 7 describes, the same way Akismet
      works, so there is no separate checkbox to defend.
- [ ] **Confirm the connector card renders.** It should appear under Settings →
      Connectors with its own type, not grouped with the AI providers, and
      saving a key should not trigger AI Client validation.
- [ ] **Build the zip**: push a `v0.2.0` tag, or run the Release workflow by
      hand, and download the artifact. Install that zip on a clean site.

## The four things that get plugins rejected

The directory names these as the most common blockers, and all four are worth
one last grep before you upload:

1. **Unescaped output.** Every echo goes through `esc_html`, `esc_attr` or
   `esc_url`.
2. **Unsanitized input.** Every `$_POST`/`$_GET`/REST value is sanitized before
   use.
3. **Missing nonces.** Form handling needs one. The Settings API supplies its
   own, and REST routes use the cookie nonce that `apiFetch` sends plus a
   `permission_callback`, so the plugin is covered. Do not add a route later
   without one.
4. **Guideline non-compliance.** Mostly the external-services disclosure for a
   plugin like this one.

## Submit

1. Sign in at https://wordpress.org/plugins/developers/add/ with your
   WordPress.org account.
2. Upload `connector-for-typesafe-jev.zip`.
3. Wait. Review takes 1 to 10 days, and they aim for 5 business days. It cannot
   be expedited, so do not ask. You get one email thread with a reviewer; reply
   in that thread rather than resubmitting.

**The slug is set by the `Plugin Name:` header and cannot be changed after
approval.** Before review begins you may get one chance to change it in the UI;
once it is under review, only plugins@wordpress.org can change it. So check the
header reads exactly `Connector for TypeSafe Jev` before you upload.

## About the name

The plugin is named **Connector for TypeSafe Jev**, not "TypeSafe Jev
Connector", on purpose. Guideline 17 prohibits a third-party trademark as the
*first* term of a plugin slug unless you can prove you own or represent the
mark. "Feature for Brand" is the form reviewers accept. The resulting slug will
be `connector-for-typesafe-jev`.

The GitHub repo is named `jev-connector` for brevity. That does not affect the
directory slug, which comes from the `Plugin Name:` header at submission time.

If TypeSafe would rather you not ship a public client under their name at all,
that is worth a short email to them before you submit. It costs you one message
and saves a takedown later.

## After approval

You get SVN access at `https://plugins.svn.wordpress.org/connector-for-typesafe-jev/`.

```bash
svn co https://plugins.svn.wordpress.org/connector-for-typesafe-jev/ svn-plugin
cd svn-plugin
# copy plugin files into trunk/, assets into assets/
svn cp trunk tags/0.1.0
svn ci -m "Release 0.1.0"
```

`Stable tag` in `readme.txt` is what the directory actually serves. Bump it and
the version header together, every release. SVN is a release repository, not a
development one — commit finished versions, not work in progress.
