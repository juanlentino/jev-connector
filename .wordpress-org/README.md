# wordpress.org assets

Files in this folder are **not** shipped in the plugin zip. Once the plugin is
approved, they go in the SVN repo's `/assets/` directory, where the directory
picks them up automatically.

Required sizes:

| File | Size | Notes |
| --- | --- | --- |
| `icon-128x128.png` | 128 × 128 | Shown in search results |
| `icon-256x256.png` | 256 × 256 | Retina |
| `banner-772x250.png` | 772 × 250 | Plugin page header |
| `banner-1544x500.png` | 1544 × 500 | Retina |
| `screenshot-1.png` | any | The card on Settings → Connectors; caption comes from readme.txt |
| `screenshot-2.png` | any | One call returning a noul, a choice and a score; caption comes from readme.txt |

Use PNG or JPG. An `icon.svg` may be supplied instead of the PNG icons, but a
256px PNG fallback is still a good idea.

Do not put the TypeSafe logo or wordmark on any of these. The plugin is an
independent client, and directory reviewers treat third-party branding on
assets as an implied endorsement.
