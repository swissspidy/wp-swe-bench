Editor-only conversion: block supports + block styles in block.json and deprecations whose migrate()
copies every legacy value into `style` as a custom value. It ignores the theme palette / spacing /
font size presets, never uses the plugin's PHP (no server-side handling, so posts that are not
re-saved keep the old inline styles and 1.0 classes on the front end, and the mobile API still
reports 1.0 default colours), and its deprecations don't tell "never chosen" defaults apart.
Expected: PHPUnit + E2E failures → reward 0.
