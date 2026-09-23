=== Acme Catalog ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later

Product catalog for the Acme shop: products, product categories and a filterable product grid.

== Description ==

* "Product" post type (`acme_product`) with price (`_acme_price`) and SKU (`_acme_sku`), and product categories (`acme_product_cat`).
* Block "Product grid" (`acme/product-grid`): category filter buttons, a search field (matches words in the product name or SKU), product count.
  Settings: heading, limit to some categories, initially selected category, search field on/off, order (name, price, newest), columns.
* Shortcode for classic pages: `[acme_products category="mugs,posters" default="mugs" search="no" orderby="price" heading="…" columns="3"]`.
* Settings → Catalog: currency symbol and position.
* Filters: `acme_catalog_product_query_args`, `acme_catalog_product_data`.

Grid markup (styled by the Acme theme):

    <div class="wp-block-acme-product-grid">
      <h2 class="acme-grid__heading">…</h2>
      <div class="acme-grid__filters" role="group" aria-label="…">
        <button type="button" class="acme-grid__filter is-active" data-category="">All</button>
        <button type="button" class="acme-grid__filter" data-category="mugs">Mugs</button>
      </div>
      <input type="search" class="acme-grid__search" …>
      <p class="acme-grid__count">Showing 3 of 12 products</p>
      <ul class="acme-grid__items"><li class="acme-grid__item" data-product-id="…">…</li></ul>
      <p class="acme-grid__empty">No products match your filters.</p>
    </div>

== Known issues ==

* The count and the initially selected category are only applied once jQuery has loaded, so the grid "jumps" after the page is displayed.

== Changelog ==

= 1.6.0 =
* New: "Initially selected category" setting.
* The search also matches SKUs; all words must match.

= 1.5.0 =
* New: `[acme_products]` shortcode.

= 1.2.0 =
* New: `acme_catalog_product_query_args` filter.

= 1.0.0 =
* Initial release.
