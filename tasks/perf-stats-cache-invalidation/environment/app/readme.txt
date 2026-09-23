=== Acme Dashboard Stats ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPLv2 or later

Newsroom numbers: posts per author, category and month, word counts and comment activity.

== Description ==

Where the numbers show up:

* Dashboard widget "Newsroom numbers" (every user who can write posts).
* Dashboard → Content stats (editors can pick an author).
* `[acme_stats]` – public site totals (About page); `[acme_stats scope="me"]` – the logged-in author's own report.
* REST: `GET /wp-json/acme-stats/v1/summary[?author=<id>]` (newsroom TV screen, mobile app).
* WP-CLI: `wp acme-stats show [--author=<id|login>] [--format=json]`.
* PHP: `acme_stats_get( $author_id = 0 )`.

Who sees what: editors and administrators see the whole site and may look at any author;
everybody else who can write posts (authors, contributors) only sees their own numbers.

What is counted: published posts (not pages, drafts, scheduled, private or trashed posts),
their categories, their local publication month, their words, and the comments on them
(approved, awaiting moderation, spam). "Most discussed" lists the posts with the most
approved comments.

= Hooks =

* `acme_stats_before_compute` ( string $scope ) – fires every time stats are computed. Our monitoring counts these.
* `acme_stats_computed` ( array $stats, string $scope ) – filters freshly computed stats.

== Changelog ==

= 1.5.2 =
* Fix: contributors could see the site-wide numbers through the REST API.

= 1.5.0 =
* REST endpoint and WP-CLI command.

= 1.4.0 =
* `[acme_stats scope="me"]`.
* "Most discussed" posts.

= 1.3.0 =
* Posts per month use the site's time zone.

= 1.0.0 =
* Initial release (dashboard widget).
