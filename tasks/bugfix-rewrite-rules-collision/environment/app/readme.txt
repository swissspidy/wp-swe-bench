=== Acme Docs ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later

Product documentation for the Acme developer site.

== Description ==

* Post type "Docs" (`doc`), hierarchical, one product per doc (`product` taxonomy).
* URLs: `/docs/{product}/` (product page), `/docs/{product}/{parent}/{doc}/` (docs), `/docs/{product}/{version}/…` (docs of another major version, `doc_version` taxonomy).
* Slugs only need to be unique within a product and version.
* Breadcrumbs and "In this section" navigation on docs.
* `[acme_doc_link product="acme-cli" path="getting-started/installation"]` cross-links.

== Changelog ==

= 1.4.1 =
* Keep rewrite rules in sync automatically (no more visiting Settings → Permalinks after adding a product).

= 1.4.0 =
* Versioned docs (`/docs/{product}/v2/…`).
* Previews use the pretty doc URL.

= 1.3.0 =
* Slugs only need to be unique within a product.
* `[acme_doc_link]` shortcode.

= 1.2.0 =
* Breadcrumbs, child docs navigation.

= 1.0.0 =
* Initial release.
