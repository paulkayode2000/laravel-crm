# Vendored browser assets

Everything in this directory is served by the application itself so that no
page load has to reach a CDN. `LOCAL-SETUP.md` explains why each one used to be
fetched remotely; this file records exactly what is stored here.

| Path | Upstream | Version | License |
| --- | --- | --- | --- |
| `tinymce/` | https://www.npmjs.com/package/tinymce | 6.6.2 | MIT (`tinymce/license.txt`) |
| `codemirror/` | https://www.npmjs.com/package/codemirror | 5.30.0 (mode), 5.13.4 (css), 5.63.3 (ayu-dark theme) | MIT |
| `chartjs-chart-funnel/` | https://www.npmjs.com/package/chartjs-chart-funnel | 4.2.1 | MIT |
| `flatpickr/` | https://www.npmjs.com/package/flatpickr | 4.6.x (`dist/themes/dark.css`) | MIT |
| `fonts/` | https://fonts.google.com/specimen/Inter | Inter variable, latin + latin-ext | SIL OFL 1.1 |

## Notes per asset

**TinyMCE.** Only the parts the editor actually uses are copied, but the upstream
directory layout is preserved because TinyMCE resolves `themes/`, `models/`,
`icons/`, `skins/` and `plugins/` relative to `tinymce.min.js`. The plugins kept
are the ones listed in `tinymce.init()`: `image media wordcount save fullscreen
code table lists link autolink`. Both skins are present (`oxide` and `oxide-dark`)
as well as the `default` and `dark` content CSS. Adding a plugin to the editor
means copying that plugin's `plugin.min.js` directory here as well.

**CodeMirror.** `codemirror.js` plus the JavaScript mode, the base stylesheet and
the `ayu-dark` theme, which is what the webhook payload editor loads.

**chartjs-chart-funnel.** The UMD minified build is byte-identical to the one the
dashboard used to load from jsDelivr.

**flatpickr.** Only the theme stylesheets are needed at runtime; flatpickr itself
is already bundled into the Vite output. `dark.css` is what the theme switcher
requests.

**Inter.** `fonts.css` is the Google Fonts `@font-face` block with `woff2` URLs
rewritten to the files next to it. Only the `latin` and `latin-ext` subsets are
kept, which is why non-Latin glyphs fall back to the system font.

## Verifying integrity

The files that were previously referenced with Subresource Integrity hashes still
match those hashes exactly:

```
tinymce/tinymce.min.js                       sha384-NjD7Hj+XpRBVk0A/j+d7Kh0xbyxAq95RK6cAaDiV4U04K8h8paDAQFpsXJzt7ZwC
codemirror/codemirror.js                     sha384-hDVkc0yoFxTaExu/IngSbi+ypUS7emvIIw+XanqgRo8oSu1ey0CR6uAzV22fezg/
codemirror/mode/javascript/javascript.js     sha384-S1JCkuNZec6d8+uXKJ+JdsXNBEZiozl0my2uuNcATHr+SVZJUqianMyjxhS+F2oS
codemirror/codemirror.css                    sha384-ayAY1F4W6brs1DOW3MU0j8FjIBc+thLUxbZVU2CXb4PnGnXfLw6zLVg4KzIAznNz
codemirror/theme/ayu-dark.min.css            sha384-xWcgiCs8RKifYguldASXfXhT717Ys5Pi3bCjgToFTInA9uIjKSsT62Peojdnp3Oz
chartjs-chart-funnel/index.umd.min.js         sha384-3s9Buv17hNMrULiw1fw1xLUac5oZZitue3rXD5XSWw8WACZcFJk1N79K4WeKwkDZ
```

To re-check on Windows:

```powershell
Get-ChildItem public\vendored -Recurse -File -Include *.js,*.css | ForEach-Object {
    $hash = [Convert]::ToBase64String(
        [System.Security.Cryptography.SHA384]::Create().ComputeHash(
            [System.IO.File]::ReadAllBytes($_.FullName)
        )
    )

    '{0} sha384-{1}' -f $_.Name, $hash
}
```

## Refreshing

1. Download the new file(s) from the upstream URL recorded in `LOCAL-SETUP.md`.
2. Replace the copy here, keeping the same relative path.
3. Update the SRI string in the table above if the file changed.
4. For flatpickr themes: the URL is baked into the compiled bundles, so also patch
   `public/admin/build/assets/app-*.js` and `public/webform/build/assets/app-*.js`
   (or rebuild them with `npm run build`, which picks the path up from
   `src/Resources/assets/js/plugins/flatpickr.js`).
