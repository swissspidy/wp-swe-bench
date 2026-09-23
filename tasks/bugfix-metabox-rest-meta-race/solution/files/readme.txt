=== Acme Product Fields ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.2
License: GPLv2 or later

Products for the Acme shop.

== Description ==

* Post type "Product" (`acme_product`).
* Product fields (see `includes/class-fields.php` for the storage format; the shop theme and the
  inventory sync read the meta keys directly):
  * Price, SKU, Badge text, Featured product, In stock: edited in the **Product details** panel of the
    block editor's document sidebar, and available as `meta` in the REST API
    (`/wp/v2/acme_product/<id>`, users who can edit the product only).
  * Internal notes, Supplier: staff only, edited in the **Product details** meta box below the editor.
    Never exposed in the REST API or on the front end.
  * In the classic editor (Settings → Product fields → "Edit products in: Classic editor"), the meta box
    contains all fields.
* Products list: Price, SKU, Stock and Featured columns. Quick Edit changes the price and the stock status,
  Bulk Edit the stock status and the featured flag ("— No Change —" leaves a product as it is).
* Front end: price, badge and stock status below the description of single products
  (`templates/product-summary.php`, overridable in the theme).
* Template tags: `acme_pf_get_price_html()`, `acme_pf_is_in_stock()`, `acme_pf_is_featured()`,
  `acme_pf_get_badge()`.

Products imported from the 1.x shop still have "yes"/"no" (stock) and "on"/"off" (featured) values;
they are read correctly everywhere and converted when the product is saved.

== Changelog ==

= 2.3.2 =
* Fix: in the block editor, the meta box request that follows every save overwrote the sidebar fields
  with the values from when the editor was opened. The meta box now only contains and saves the staff
  fields there.
* Fix: every surface only saves the fields it shows (classic meta box, block editor meta box, Quick Edit,
  Bulk Edit). Quick Edit no longer clears the SKU, badge, notes and supplier.
* Fix: unchecking "Featured product" / "In stock" in the classic editor and in Quick Edit is saved.
* Fix: quotes and backslashes no longer get extra backslashes when saving the meta box.
* Fix: Bulk Edit "— No Change —" left products unchanged instead of setting "No".
* Fix: products imported from 1.x ("yes"/"no", "on"/"off") show the right stock/featured state in the
  block editor and the REST API.

= 2.3.1 =
* Fix: saving the meta box in the block editor cleared the sidebar fields (#41).

= 2.3.0 =
* Quick Edit (price, stock) and Bulk Edit (stock, featured).

= 2.2.0 =
* Internal notes and supplier fields.

= 2.0.0 =
* Block editor: "Product details" panel in the document sidebar; fields registered for the REST API.
* Setting to keep using the classic editor for products.

= 1.0.0 =
* Initial release (classic editor meta box).
