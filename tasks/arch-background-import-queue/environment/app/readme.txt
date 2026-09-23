=== Acme Importer ===
Contributors: acmeweb
Tags: products, catalog, csv, import
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPL-2.0-or-later

Product catalog for Acme shops, with a CSV importer for supplier price lists.

== Description ==

Products (`acme_product`) have a SKU, a price and a stock level. Shop managers
(`acme_shop_manager` role, or anyone with the `acme_import_products` capability)
upload supplier price lists under **Products → Import**.

= CSV format =

A header row, then one product per row. Columns (in any order, case-insensitive;
supplier aliases like `Artikelnummer`, `qty` or `Preis` are understood):

* `sku` (required): products are matched by SKU. Rows without a SKU are skipped.
* `name`
* `price`: `12.50`, `12,50`, `1.234,50` and `1,234.50` are understood; stored in cents.
* `stock`: a whole number, or `-` for "not tracked".
* `status`: publish, draft, pending or private. New products get the status from the settings.
* `categories`: separated by `|`. Missing categories are created.
* `description`

Empty cells leave the existing value unchanged. Comma, semicolon and tab
delimited files are supported.

= Hooks =

* `acme_importer_row_data` (array|false $data, array $row, int $row_number): filter the
  product data of a row; return false to skip it.
* `acme_importer_product_saved` (int $id, array $data, bool $created): after a product was
  saved by the importer. The search index and the ERP sync listen to it.
* `acme_importer_finished` (array $result, string $file_name)

== Changelog ==

= 2.2.0 =
* New: supplier header aliases (Artikelnummer, item_no, Preis, qty…).
* New: semicolon and tab delimited files.

= 2.1.0 =
* New: summary of the last import with the first 50 errors.
* Fix: prices with thousands separators.

= 2.0.0 =
* Changed: SKUs are stored upper-case, prices in cents.
* New: categories column.

= 1.0.0 =
* First release.
