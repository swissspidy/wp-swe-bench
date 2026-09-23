=== Acme Loyalty ===
Contributors: acmeweb
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.3.1
License: GPL-2.0-or-later

Loyalty points, member tiers, the store newsletter and the POS order sync for Acme Coffee Roasters.

== Description ==

* Members (role "Loyalty member", or any account enrolled in store) collect points for completed orders.
* Tiers (Bronze / Silver / Gold) from lifetime points, filterable with `acme_loyalty_tiers`.
* `[acme_loyalty_account]` – "My loyalty" page: balance, history, contact preferences.
* `[acme_newsletter]` – newsletter sign-up form with double opt-in. No account needed.
* Orders are synced from the POS (`wp acme-loyalty import-orders`) as private `acme_order` posts with order notes.

= Where data lives =

* `{prefix}acme_loyalty_ledger` – points ledger. Append-only; finance reconciles it against the POS each quarter.
* `{prefix}acme_loyalty_subscribers` – newsletter subscribers (by email address, guests included).
* User meta `acme_loyalty_*` – member profile (see `includes/class-members.php`).
* Post meta `_acme_order_*` on `acme_order` posts – orders and order notes (see `includes/class-orders.php`).

= Hooks =

* `acme_loyalty_tiers` (filter)
* `acme_loyalty_points_for_order` (filter)
* `acme_loyalty_points_awarded` (action)
* `acme_loyalty_order_status_changed` (action)

== Changelog ==

= 2.3.1 =
* Fix: welcome bonus was awarded twice when an account was enrolled in store and online.

= 2.3.0 =
* Staff notes on orders can be marked as visible to the customer.
* Orders list: email and total columns.

= 2.2.0 =
* Ledger: optional note per movement (staff comments, order numbers).
* Newsletter: record when someone unsubscribed.

= 2.1.0 =
* Newsletter addresses are stored lower-cased (older rows keep the original spelling, see ACME-311).

= 2.0.0 =
* Order notes are now a list (customer + staff notes). The 1.x single delivery note is still read.
* Birthday stored as Y-m-d (`acme_loyalty_birthday`), preferences as an array (`acme_loyalty_preferences`). 1.x values are converted when the member next saves their preferences.
* Subscribers can be linked to an account.

= 1.4.0 =
* Points ledger.

= 1.0.0 =
* Newsletter and member profiles.
