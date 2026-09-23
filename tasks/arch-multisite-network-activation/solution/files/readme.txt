=== Acme Directory ===
Contributors: acmeweb
Tags: directory, listings, business directory, local
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.5.0
License: GPLv2 or later

Local business directory: listings, categories, a submission form, moderation and a public REST API.

== Description ==

* `[acme_directory category="food" limit="10"]` lists published listings.
* `[acme_directory_submit]` lets logged-in members suggest a listing (held for moderation by default).
* Directory → Listings is the moderation queue; Directory → Settings has the per-site settings.
* REST API (public, read-only unless logged in):
  * `GET /wp-json/acme-directory/v1/listings?category=&search=&page=&per_page=`
  * `GET /wp-json/acme-directory/v1/listings/<id>`
  * `POST /wp-json/acme-directory/v1/listings` (logged-in members)
  * `GET /wp-json/acme-directory/v1/categories`
* A daily cleanup event (`acme_directory_daily_cleanup`) expires listings and purges stale submissions.
  `wp acme-directory cleanup` runs it on demand.

= Data =

Each site that uses the directory has two tables, `{prefix}acme_dir_listings` and
`{prefix}acme_dir_categories`, and the options `acme_directory_settings`,
`acme_directory_db_version` and `acme_directory_installed_at` (plus the
`acme_directory_counts` transient). Deactivating the plugin keeps the data; deleting
the plugin removes it.

= Multisite =

The plugin can be activated on individual sites or for the whole network.

* Network activation sets the directory up on every site of the network. Up to 25 sites
  are set up right away; on larger networks the remaining sites are set up in the
  background, 25 sites per run of the main site's scheduled events. A site that has not
  been reached yet sets itself up on its first request.
* Sites created while the plugin is network-active are set up immediately.
* Deleting a site removes its directory tables.
* Network deactivation keeps all data and removes the scheduled cleanup from every site
  on which the plugin is not activated individually.
* Deleting the plugin removes its tables, options and scheduled events from every site.

= Hooks =

* `acme_directory_installed` (action): fires after the directory was set up on a site.
* `acme_directory_default_settings` (filter): default settings for newly set-up sites.
* `acme_directory_listing_saved` (action): a listing was created.
* `acme_directory_cleanup_done` (action): the daily cleanup ran.
* `acme_directory_table( 'listings'|'categories' )`: table name for custom queries.

== Changelog ==

= 2.5.0 =
* Multisite: network activation (batched on large networks), new sites are set up
  automatically, deleted sites lose their directory tables, network deactivation and
  uninstall handle every site.
* Fix: table names were resolved once per request and pointed at the wrong site after
  switching sites.

= 2.4.1 =
* Fix: category counts were not refreshed after moderation.

= 2.4.0 =
* Categories moved from a hard-coded list to their own table (DB version 3).
* New: `acme_directory_installed` action.

= 2.3.0 =
* New: `wp acme-directory cleanup`.
* Unmoderated submissions are purged after 30 days (configurable).

= 2.2.0 =
* Listings expire (daily cleanup event).

= 2.0.0 =
* Listings moved from a custom post type to a custom table.
* Public REST API.

= 1.0.0 =
* First release.
