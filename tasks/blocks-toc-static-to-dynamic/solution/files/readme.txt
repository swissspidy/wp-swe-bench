=== Acme TOC ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

A "Table of Contents" block for long articles and documentation pages.

== Description ==

* Block: "Table of Contents" (`acme/toc`). Lists the headings of the post with links to them.
* Choose the deepest heading level to include (H2–H6) and skip headings inside certain blocks.
* Headings inside `core/details` are never listed. Themes/plugins can change this list with the `acme_toc_excluded_blocks` filter.
* Settings → Table of Contents: default depth for new blocks, smooth scrolling.

Front-end markup (styled by the Acme theme):

    <nav class="wp-block-acme-toc" aria-label="…">
      <p class="acme-toc__title">…</p>
      <ol class="acme-toc__list">
        <li class="acme-toc__item acme-toc__item--h2"><a href="#anchor">…</a></li>
      </ol>
    </nav>

== Changelog ==

= 2.0.0 =
* The table of contents is now rendered on the server from the current headings of the post, so it can't go stale. Existing TOCs are upgraded automatically (no need to re-save posts).
* New: "Highest heading level" setting (H2 by default).
* Headings without an HTML anchor get one on the front end (same scheme as before); duplicate headings get unique anchors and existing anchors are never changed.
* Paginated posts: links to headings on other pages point at the right page.
* The default title is translatable.

= 1.4.2 =
* Fix: the "Deepest heading level" control could be set to 1.

= 1.4.0 =
* New: "Skip headings inside these blocks" setting per block.
* New: smooth scrolling option.

= 1.3.0 =
* Markup: the TOC is now a `<nav>` landmark with an ordered list (`acme-toc__list`/`acme-toc__item`). The title is a paragraph and defaults to "Table of contents".
* Headings without an HTML anchor get one automatically.

= 1.2.0 =
* New: `acme_toc_excluded_blocks` filter; headings inside Details blocks are skipped.

= 1.0.0 =
* Initial release.
