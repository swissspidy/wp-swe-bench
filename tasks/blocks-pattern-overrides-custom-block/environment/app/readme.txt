=== Acme Call to Action ===
Contributors: acmeweb
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later

A call-to-action block (heading + button) with campaign click tracking.

== Description ==

* Block: "Call to action" (`acme/cta`) with a heading (level 2–4), a button (text, link, "open in new tab"), a variant and an optional campaign.
* Variants: primary, secondary, dark. Themes can add variants with the `acme_cta_variants` filter (and must style `.wp-block-acme-cta.is-variant-{slug}`).
* Settings → CTA tracking: outgoing CTA links (to other hosts) get `utm_source`, `utm_medium` and `utm_campaign` parameters and a `data-acme-cta` attribute for the analytics snippet. The final URL can be changed with the `acme_cta_tracked_url` filter.
* Tools → CTA inventory and `wp acme-cta list [--post_type=<type>] [--format=<format>]` list every CTA on the site (including CTAs inside synced patterns).

Most CTAs on our sites live in synced patterns ("Newsletter signup", "Webinar", …) that are reused across many pages.

== Changelog ==

= 2.3.0 =
* CTA inventory (Tools screen + WP-CLI command).

= 2.2.0 =
* Heading level control (h2–h4).

= 2.1.0 =
* Campaign tracking settings.

= 2.0.0 =
* New markup (`wp-block-acme-cta__heading`, `wp-block-acme-cta__button`), variants instead of colours.
* 1.x CTAs (`acme-cta`, `acme-cta--{color}`) keep working in the editor.

= 1.0.0 =
* Initial release.
