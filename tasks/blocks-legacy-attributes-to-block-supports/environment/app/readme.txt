=== Acme Content Blocks ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Notice boxes and statistics for the Acme sites.

== Description ==

* "Notice box" (`acme/notice-box`): a box with a tone (info, success, warning, error – filterable with `acme_content_blocks_tones`), an optional icon, an optional border and any content inside. Colours, padding and font size are picked in the block sidebar.
* "Statistic" (`acme/stat`): a big number with a label. Number colour and size are picked in the block sidebar.
* REST API for the Acme mobile app: `GET /wp-json/acme-blocks/v1/posts/<id>/notices` returns the notices of a post (`tone`, `background`, `text`, `padding`, `font_size`, `outlined`, `content`). Colours are hex strings, sizes are pixel numbers, `null` when not set.

== Changelog ==

= 1.6.0 =
* Notice box: optional border.

= 1.5.0 =
* Statistic block.

= 1.3.0 =
* Notice box: new markup (`wp-block-acme-notice-box`, `is-tone-{tone}`, icon + body elements); only chosen colours and sizes are saved inline.
* 1.0 notices keep working.

= 1.0.0 =
* Initial release: notice box with inline colours, padding (20px) and font size (16px).
