=== Acme Corporate ===
Contributors: acmewebteam
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later

Corporate theme for acme-corp.example.

== Description ==

Classic theme (PHP templates) with a theme.json for block styles.

* Menu locations: Primary (header), Footer.
* Widget areas: Sidebar (blog), Footer (office address etc.).
* Customizer: tagline toggle, header button, footer text ({year} / {site} placeholders), social links, contact details.
* Block patterns: Hero, Services, Testimonials, Call to action.

= Filters =

* `acme_corporate_social_links( $links )`

== Changelog ==

= 4.0.0 =
* New: the header and footer are block template parts (parts/header.html, parts/footer.html) and can be
  edited in Appearance → Editor → Patterns. All PHP templates render them.
* The header and footer show the existing Customizer settings (tagline, header button, footer text,
  social links, contact details), the Footer widget area and the classic menus assigned to the
  Primary / Footer locations, until they are customized in the Site Editor.
* Patterns moved to /patterns (same slugs). New synced "Contact card" pattern, created once.

= 3.4.1 =
* Fix: submenu toggle had no accessible name.

= 3.4.0 =
* New: theme.json (colors, layout) so the block editor matches the front end.
* New: Call to action pattern.

= 3.0.0 =
* Social links are stored in one `acme_corporate_social_links` setting. The 2.x settings
  (`acme_corporate_twitter_url`, …) are still read as a fallback.

= 2.0.0 =
* New: header button, contact details.
