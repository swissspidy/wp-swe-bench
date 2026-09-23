=== Acme Related ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.0
License: GPLv2 or later

"Related reading" under posts: editor picks first, then the posts that share the most tags and categories.

== Description ==

* Appended to posts (Settings → Related reading: below single posts, below posts everywhere, or nowhere).
* Block: "Related posts" (`acme/related-posts`), optionally for another post (`postId`), with its own count/heading.
* REST API: `acme_related` field on posts (`/wp/v2/posts`), `GET /acme-related/v1/related/<id>` and the view beacon `POST /acme-related/v1/views/<id>`.
* Template tags: `acme_related_get_ids()`, `acme_related_get_items()`, `acme_related_the_list()`.
* Per post (edit screen, "Related reading" box): editor picks, primary category, "never show as related", "no list under this post".

= Ranking =

Every shared tag counts 2 points, every shared category 1 point. Ties: newer post first, then higher ID.
Editor picks always come first, in the order entered. Posts flagged "never show as related" are skipped
(unless picked by an editor). Only published posts are shown.

= Filters =

* `acme_related_post_ids` ( int[] $ids, int $post_id ) – ordered IDs before the list is cut to size.
* `acme_related_item_data` ( array $data, WP_Post $post ) – data of one item (also used by the REST field).
* `acme_related_item_html` ( string $html, array $item ) – HTML of one item.
* `acme_related_list_html` ( string $html, int $post_id, array $items ) – HTML of the whole list.
* `acme_related_show_in_content` ( bool $show, WP_Post $post ) – whether the list is appended to the content.

= Data =

* Editor picks: post meta `_acme_related_manual` (array of IDs; 1.x stored a comma separated string, still supported).
* "Never show as related": `_acme_related_exclude` (`1`; 1.x stored `yes`).
* "No list under this post": `_acme_related_hide`.
* Primary category: `_acme_primary_category`.
* Reading time (minutes): `_acme_reading_time`, stored on save since 2.0; estimated for older posts.
* Views: table `{prefix}acme_related_views`.

== Changelog ==

= 2.4.0 =
* Performance: related lists of all posts on a page (archives, REST collections, query loops) are computed together, and the posts, authors, images, terms and view counts they show are loaded in bulk. Archive pages went from ~900 to a few dozen queries.
* Computed lists are kept in the object cache and invalidated whenever posts, post meta or terms change.

= 2.3.1 =
* Fix: editor picks pointing at trashed posts were shown.

= 2.3.0 =
* `acme_related` REST field and `/acme-related/v1/related/<id>` route for the app.
* Block can show the list of another post.

= 2.2.0 =
* "Related posts" block.
* Primary category.

= 2.1.0 =
* View counter (custom table) and "views" in the list.

= 2.0.0 =
* Editor picks are stored as an array. Old comma separated values keep working.
* Reading time stored on save.
* Display setting "below posts everywhere".

= 1.2.0 =
* Ranking by shared tags (2 points) and categories (1 point).

= 1.0.0 =
* Initial release.
