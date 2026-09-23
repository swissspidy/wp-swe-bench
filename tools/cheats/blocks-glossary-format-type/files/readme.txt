=== Acme Glossary ===
Contributors: acmedocs
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Glossary of technical terms for the Acme developer docs: term pages, tooltips in articles and an A–Z index.

== Description ==

* Post type "Glossary" (`glossary_term`, archive at /glossary/). Each term has a title, a long description
  (content) and a "Short definition" (meta `_acme_glossary_short`) used for tooltips and the index.
  Terms are edited in the classic screen.
* Inline terms in articles (classic editor era):
  `[glossary term="cdn"]edge caching[/glossary]` (term by slug or title),
  `[glossary id="42"]TTFB[/glossary]` (term by ID) or `[glossary]Cache[/glossary]` (the text is the term).
  The front end shows the term's short definition in a tooltip.
* Block editor: select text and use "Glossary term" in the formatting toolbar to link it to a term
  (stored as `<span class="acme-glossary-term" data-term-id="42">…</span>`). The front end adds an
  accessible tooltip with the term's current short definition; marks of deleted or unpublished terms
  are shown as plain text. `[glossary]` shortcodes are converted when classic content is converted to blocks.
* `[glossary_index]` shortcode and "Glossary index" block: all terms A–Z.
* REST: `GET /wp-json/acme-glossary/v1/terms?search=…` lists published terms (id, slug, title, definition, link).
* Filter `acme_glossary_definition` ( $definition, $term_post ): change the short definition of a term
  (the docs theme appends notes to deprecated terms with it).

== Theme functions ==

* `acme_glossary_get_definition( $term )` – short definition (plain text).
* `acme_glossary_find_term( $id_or_slug_or_title )` – published term post or null.
* `acme_glossary_archive_url()`.

== Changelog ==

= 2.0.0 =
* New "Glossary term" text format in the block editor, with term search.
* New accessible tooltip markup (also for `[glossary]` shortcodes); no more `title` tooltips.
* `[glossary]` shortcodes become marked text when classic content is converted to blocks.
* Definitions are always current: the term cache is flushed when a term's definition or status changes.

= 1.8.0 =
* REST endpoint for terms; "Glossary index" block.

= 1.7.0 =
* Lookup map of all terms is cached (one query per page instead of one per term).

= 1.5.0 =
* `[glossary]` accepts `id="…"`; term titles are matched case-insensitively.

= 1.2.0 =
* Short definition field.

= 1.0.0 =
* Initial release: glossary post type, [glossary] and [glossary_index] shortcodes.
