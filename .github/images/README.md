# Repository images

Nothing in this folder ships in the plugin zip (`.github` is excluded by
`.distignore`). These are for the GitHub README and the repository's social
preview only. Directory listing assets are a separate set; see
[`.wordpress-org/README.md`](../../.wordpress-org/README.md).

## Present

| File | Size | Where it is used |
| --- | --- | --- |
| `connectors-card.png` | 700 × 450 | README, Screenshots. Captured from a live WordPress 7.1 site, cropped to the card list. Also copied to `.wordpress-org/screenshot-1.png`. |
| `answers.png` | 1790 × 536 | README, Screenshots. One `wp eval` call on the live site, cropped to the call and its output: the shell prompt, the host paths and the status bar (which carried the server IP) are outside the frame. Also `.wordpress-org/screenshot-2.png`. |
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

## Capturing more

Nothing outstanding. If you replace either image, keep the rules that applied
to these two:

- **No credential, no host.** The Connectors screen renders no key in the
  connected state, and a response body carries none; a request inspector
  showing `Authorization` does. A terminal shot leaks more than the command:
  the prompt carries the SSH user, the header block carries the document
  root, and the status bar carried the server's IP. Crop to the command and
  its output.
- **No other plugin's rows.** The Settings tab strip lists whatever else is
  installed; leave it out of frame.
- Capture at 2x and let GitHub scale down.
