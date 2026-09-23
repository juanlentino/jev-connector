# Repository images

Nothing in this folder ships in the plugin zip (`.github` is excluded by
`.distignore`). These are for the GitHub README and the repository's social
preview only. Directory listing assets are a separate set; see
[`.wordpress-org/README.md`](../../.wordpress-org/README.md).

## Present

| File | Size | Where it is used |
| --- | --- | --- |
| `connectors-card.png` | 700 × 450 | README, Screenshots. Captured from a live WordPress 7.1 site, cropped to the card list. Also copied to `.wordpress-org/screenshot-1.png`. |
| `social-preview.png` | 1280 × 640 | GitHub **Settings → General → Social preview**. Upload by hand: the API cannot set it. |
| `social-preview.svg` | source | Regenerate the PNG after editing (see below). |

Regenerating the preview, on macOS with no extra tooling:

```bash
qlmanage -t -s 1280 -o . social-preview.svg
sips -c 640 1280 --cropOffset 320 0 social-preview.svg.png --out social-preview.png
rm social-preview.svg.png
```

The SVG is 1280 × 1280 with the artwork as a centred 1280 × 640 band, because
Quick Look renders any SVG into a square canvas. Rendering a 1280 × 640 SVG
directly makes it scale to fit and clips the right edge.

## Still to capture

One image, referenced from the README's Screenshots section, which is
commented out until it exists. Uncomment it in the same commit that adds it.

### `answers.png` — one call, three answers

A single `ask()` returning a `noul`, a `choice` and a `score`, each with its
confidence. Either a terminal running the example through WP-CLI, or the
JSON body from `POST /wp-json/jev/v1/ask`.

- Use a mocked or already-cached response rather than spending a real call,
  or redact nothing and spend one: the body carries no credential either way.
- The `Authorization` header must not appear. It is not in the response body,
  so a body-only shot is safe; a shot of a request inspector is not.
