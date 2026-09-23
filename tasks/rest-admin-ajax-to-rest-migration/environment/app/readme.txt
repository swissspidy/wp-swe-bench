=== Acme Inventory ===
Contributors: acmeshop
Tags: inventory, stock, warehouse
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.2.1
License: GPLv2 or later

Stock levels for the Acme shop warehouse.

== Description ==

* **Inventory** screen for users with the `manage_acme_inventory` capability (administrators and
  Shop Managers; warehouse staff accounts get it individually).
* Inline stock edits, bulk adjustments with a reason, CSV export for the Monday stock count.
* Every stock change is written to the adjustment log and fires `acme_inventory_stock_changed`.
* Low-stock e-mail alert when an item drops to its threshold (Settings: `acme_inventory_alert_email`,
  defaults to the admin e-mail).

The barcode-scanner page and the label printer integration call the admin-ajax actions
directly (see includes/class-ajax.php).

== Changelog ==

= 3.2.1 =
* Fix: search also matches SKUs.

= 3.2.0 =
* Low stock only filter.
* Bulk adjustment reason is stored in the log.

= 3.1.0 =
* CSV export.

= 3.0.0 =
* New inventory screen (jQuery) replacing the old list table.
* Items moved from post meta to the `acme_inventory_items` table (items migrated in 2.x have no
  "updated" date).
