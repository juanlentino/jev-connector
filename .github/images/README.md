# Repository images

Nothing in this folder ships in the plugin zip (`.github` is excluded by
`.distignore`). These are for the GitHub README and the repository's social
preview only. Directory listing assets are a separate set; see
[`.wordpress-org/README.md`](../../.wordpress-org/README.md).

## Present

| File | Size | Where it is used |
| --- | --- | --- |
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

Two images, both referenced from the README's Screenshots section, which is
commented out until they exist. Uncomment it in the same commit that adds them.

### 1. `connectors-card.png` — the card

**Settings → Connectors**, with the TypeSafe Jev card connected. Frame the
card list so Anthropic and Google are visible above it: the point of the shot
is that Jev sits among the AI providers while not being one.

- No key is on screen in this state. Core renders "Connected" and an Edit
  link, never the key. Check before saving the file anyway.
- Crop to the card list. Leave out the admin menu, the site name and any
  other plugin's rows.
- A 2x (Retina) capture, roughly 1800 px wide before scaling, keeps the text
  crisp on GitHub.

### 2. `answers.png` — one call, three answers

A single `ask()` returning a `noul`, a `choice` and a `score`, each with its
confidence. Either a terminal running the example through WP-CLI, or the
JSON body from `POST /wp-json/jev/v1/ask`.

- Use a mocked or already-cached response rather than spending a real call,
  or redact nothing and spend one: the body carries no credential either way.
- The `Authorization` header must not appear. It is not in the response body,
  so a body-only shot is safe; a shot of a request inspector is not.
