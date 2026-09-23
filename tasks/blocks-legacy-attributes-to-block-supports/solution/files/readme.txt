=== Acme Content Blocks ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Notice boxes and statistics for the Acme sites.

== Description ==

* "Notice box" (`acme/notice-box`): a box with a tone (info, success, warning, error – filterable with `acme_content_blocks_tones`), an optional icon and any content inside. Uses the standard Color, Dimensions (padding) and Typography (font size) tools with the theme's presets, and the "Outlined" block style.
* "Statistic" (`acme/stat`): a big number with a label. Uses the standard Color (text) and Typography (font size) tools and the "Card" block style.
* The theme controls the defaults of both blocks through theme.json (`styles.blocks["acme/notice-box"]`, `styles.blocks["acme/stat"]`, including the `outlined` and `card` variations).
* REST API for the Acme mobile app: `GET /wp-json/acme-blocks/v1/posts/<id>/notices` returns the notices of a post (`tone`, `background`, `text`, `padding`, `font_size`, `outlined`, `content`). Colours are hex strings, sizes are pixel numbers, `null` when not set.

== Changelog ==

= 2.0.0 =
* Both blocks use the editor's design tools (colour palette, spacing and font size presets) and block styles (Outlined, Card) instead of bespoke controls.
* Existing notices (1.0 and 1.3+) and statistics are converted when opened in the editor: palette colours become presets, other hex colours become custom colours, matching pixel sizes become presets. Defaults that were never chosen are dropped so the theme's defaults apply.
* Blocks are rendered on the server, so posts that are never re-saved get the new classes and styles too.
* The plugin CSS no longer overrides the theme's styles.
* Mobile app API: colours and sizes are resolved from presets; 1.0 default colours are reported as `null`.

= 1.6.0 =
* Notice box: optional border.

= 1.5.0 =
* Statistic block.

= 1.3.0 =
* Notice box: new markup (`wp-block-acme-notice-box`, `is-tone-{tone}`, icon + body elements); only chosen colours and sizes are saved inline.
* 1.0 notices keep working.

= 1.0.0 =
* Initial release: notice box with inline colours, padding (20px) and font size (16px).
