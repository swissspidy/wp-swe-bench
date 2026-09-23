=== Acme Orders Sync ===
Contributors: acmecorp
Tags: orders, webhooks, shop
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.2
License: GPL-2.0-or-later

Receives order webhooks from the Acme Shop storefronts and keeps a local copy of every order.

== Description ==

Every storefront (source) is configured under **Settings → Order Sync** with an ID
(`shop-eu`, `shop-us`, …) and the webhook secret shown in the shop's admin. The shop
sends its events to `POST /wp-json/acme-orders/v1/webhook` with the `X-Acme-Source`
header set to the source ID. `GET /wp-json/acme-orders/v1/ping` lets the shop check
the URL.

Events are applied to local `acme_order` posts (Shop orders menu). Other code can react to

* `acme_orders_pre_sync_order` (filter) – change the normalized order, or return a
  `WP_Error` to abort the sync of an event;
* `acme_orders_order_synced` (action) – `( $post_id, $order, $event, $source )`, fired
  after an event was applied. Our fulfilment glue code hooks in here;
* `acme_orders_logged` (action) – every log entry.

The sync log (Tools → Order Sync Log) lives in
`wp-content/uploads/acme-orders-sync/sync.log`, one JSON object per line.

= WP-CLI =

* `wp acme-orders replay <file> [--source=<id>]` – apply an event JSON file right away.
* `wp acme-orders sources` – list the configured storefronts.

== Changelog ==

= 1.3.2 =
* Fix: refunds on orders created before 1.2 added up the wrong totals.

= 1.3.0 =
* Multiple storefronts (sources). The 1.0 webhook secret is used for the `default` source.
* Log viewer under Tools.

= 1.2.0 =
* Order totals are stored in minor units. Existing orders are migrated.
* Shop webhook API v2 payloads (`data.order`).

= 1.1.0 =
* `wp acme-orders replay`.

= 1.0.0 =
* First release. One storefront, shared secret sent by the shop in `X-Acme-Token`.
