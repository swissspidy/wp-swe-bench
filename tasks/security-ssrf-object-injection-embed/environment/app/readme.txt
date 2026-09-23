=== Acme Link Previews ===
Contributors: acmeweb
Tags: link, preview, embed, opengraph
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later

Turn a URL into a rich preview card (title, description, image) by fetching the remote page, and cache the result.

== Description ==

Acme Link Previews fetches a remote page, reads its Open Graph tags and renders a tidy preview card.

* Shortcode: `[acme_link_preview url="https://example.com/"]`.
* A REST endpoint the block editor uses to fetch a preview while you type.
* Preview results are cached so a page render never hits the network.
* Import / export the whole preview cache between sites.
* Visitors can pick a light/dark card theme; the choice is remembered in a cookie.

Developers can override how a host name is resolved to an IP address (for split-horizon
DNS or a caching resolver) with the `acme_lp_resolve_host` filter.

== Changelog ==

= 2.1.0 =
* Import / export of the preview cache.
* Light / dark card themes remembered per visitor.

= 2.0.0 =
* Rewrote the fetcher; follow redirects one hop at a time.

= 1.0.0 =
* Initial release.
