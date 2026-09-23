=== Acme Media Card ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

A "Media card" block (`acme/media-card`): an image (optionally linked), a heading, some text and a call to action.

== Description ==

* Block styles: Outlined, Elevated.
* External call-to-action links get `rel="noopener"` and the `is-external` class on output.
* Pattern: "Three promo cards" (category "Acme").

Markup (themes style these classes):

    <div class="wp-block-acme-media-card has-layout-{layout} is-{cardType}-card">
      <figure class="wp-block-acme-media-card__media"><a href="…"><img src="…" alt="…" class="wp-image-123"/></a></figure>
      <div class="wp-block-acme-media-card__content">
        <p class="wp-block-acme-media-card__meta">…</p>
        <h3 class="wp-block-acme-media-card__heading">…</h3>
        <p class="wp-block-acme-media-card__text">…</p>
        <a class="wp-block-acme-media-card__cta" href="…">…</a>
      </div>
    </div>

== Changelog ==

= 2.0.0 =
* Variations: Product card, Profile card, Event card (`cardType`), each with its own defaults.
* Layout option (`stacked`, `media-left`, `media-right`); cards carry `has-layout-{layout}` and `is-{type}-card` classes.
* Optional meta line (`__meta`) above the heading (price, role, date).
* Transforms from Image and Media & Text blocks, and to Media & Text.
* Cards from 1.x are upgraded when edited.

= 1.2.0 =
* Block styles "Outlined" and "Elevated".
* Pattern "Three promo cards".

= 1.1.0 =
* Images can be linked. New class names: `__heading` (was `__title`, now an `h3`), `__cta` (was `__button`).
  1.0 cards are upgraded when edited.

= 1.0.0 =
* Initial release.
