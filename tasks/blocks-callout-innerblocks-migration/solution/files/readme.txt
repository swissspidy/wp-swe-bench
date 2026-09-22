=== Acme Callouts ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Callout boxes (info, success, warning, danger) for the block editor, plus the legacy [callout] shortcode.

== Description ==

* Block: "Callout" (`acme/callout`) with a type, an optional title and text.
* Shortcode (legacy, from the classic-editor days): `[callout type="warning" title="Heads up"]Text[/callout]`.
* Settings → Callouts: default type, and whether old shortcodes are still rendered.
* Themes can register extra types with the `acme_callouts_types` filter.
* The posts list shows how many callouts each post contains.

== Changelog ==

= 2.0.0 =
* The callout body now holds blocks (paragraphs, headings, lists).
* New accessible markup (`<aside role="note">`, `is-type-{type}`), rendered on the server for all callouts, including 1.x content and [callout] shortcodes.
* Converting classic content turns [callout] shortcodes into callout blocks.

= 1.4.0 =
* Show callout counts on the posts list.

= 1.3.0 =
* New markup with BEM classes (`acme-callout`, `acme-callout--{type}`, `acme-callout__title`, `acme-callout__content`).
* Old 1.x callouts keep working.

= 1.2.0 =
* Settings screen.

= 1.0.0 =
* Initial release.
