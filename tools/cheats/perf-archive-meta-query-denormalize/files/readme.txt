=== Acme Real Estate ===
Contributors: acme-web-team
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Property listings for Acme Realty: listing post type, search form, public REST API, CSV import and export.

== Description ==

* Listings (`acme_listing`) with price, bedrooms, bathrooms, living area, city, features and sale status, edited in the "Listing details" box.
* `[acme_listing_search]` – search form and results (the "Find a home" page).
* REST: `GET /wp-json/acme-re/v1/listings` – public search (mobile app, partner portals).
* WP-CLI: `wp acme-listings export` (search → CSV/JSON), `wp acme-listings import <file.csv>` (nightly MLS feed), `wp acme-listings reindex`.

= For developers =

* `acme_re_search( $args )` – see `includes/class-search.php` for the arguments; returns IDs, total and pages.
* `acme_re_get_listing( $post )` – public data of a listing.
* Filters: `acme_re_search_args` (normalized search arguments), `acme_re_listing_data`, `acme_re_features`, `acme_re_cities`.
* Listing data is stored in post meta (`_acme_price`, `_acme_bedrooms`, `_acme_bathrooms`, `_acme_sqft`, `_acme_city`, `_acme_features`, `_acme_status`). The CRM sync and the agency's own importer write these directly.
* The search runs against a search index (`{prefix}acme_listing_index`, `{prefix}acme_listing_features`) that is updated whenever the searchable meta changes, however it is written. `wp acme-listings reindex` rebuilds it from the meta.

== Changelog ==

= 1.7.0 =
* Performance: the search (form, REST, CLI export) no longer runs post meta queries with LIKE on serialized features; it uses an indexed lookup table kept in sync with the listing meta. Results and order are unchanged.
* The REST API and the search form load the listings of a page in a constant number of queries.
* New `wp acme-listings reindex` command. The index is backfilled automatically on update.

= 1.6.2 =
* Fix: sorting by price treated "425000.00" (old imports) as text.

= 1.6.0 =
* "Most bedrooms" sort order.
* REST: `status` and `sort` parameters.

= 1.5.0 =
* WP-CLI export.
* Search: "Sale pending" listings are shown together with listings for sale by default.

= 1.4.0 =
* Public REST API.

= 1.2.0 =
* Features are stored as a list of slugs (1.0/1.1 stored a map of slug => "yes"; both are read).

= 1.0.0 =
* Initial release.
