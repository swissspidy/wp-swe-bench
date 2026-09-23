=== Acme Specs ===
Contributors: acmefurniture
Tags: products, specifications, catalogue
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.1
License: GPLv2 or later

Product catalogue with technical specifications: dimensions, materials and certifications.

== Description ==

Adds a "Products" post type (`/wp/v2/products` in the REST API) with a "Product specifications" box.
The specifications are shown in a table below the product description and can be embedded
anywhere with `[acme_specs id="123"]`.

Developers:

* `acme_specs_certification_codes` filter: certification codes editors can choose (code => label).
* `acme_specs_get` filter: a product's specs before they are displayed.
* `acme_specs_saved` action: fires after the specifications box saved a product.
* `acme_specs_get( $post )` template tag.

== Changelog ==

= 2.4.1 =
* Fix: comma decimal separators ("35,5") in the dimension fields.

= 2.4.0 =
* Rendered specification tables are cached (category pages with many [acme_specs] shortcodes were slow).

= 2.3.0 =
* "Dimensions" and "Certifications" columns on the Products screen.

= 2.2.0 =
* Certifications can have an expiry date.

= 2.0.0 =
* Specifications are stored as structured data (`_acme_specs_dimensions`, `_acme_specs_materials`,
  `_acme_specs_certifications`). Products saved with 1.x keep working: their old fields are read
  until the product is saved again.
* Products use the block editor; specifications stay in the box below the editor.

= 1.3.0 =
* Certification codes can be extended with `acme_specs_certification_codes`.

= 1.0.0 =
* Initial release (one field per spec: `_acme_width`, `_acme_height`, `_acme_depth`, `_acme_unit`,
  `_acme_materials`, `_acme_certs`).
