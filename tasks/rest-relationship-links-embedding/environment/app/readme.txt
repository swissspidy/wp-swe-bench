=== Acme Library ===
Contributors: acmepublishing
Tags: books, authors, catalogue
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.3.1
License: GPL-2.0-or-later

Books and authors for the Acme Publishing website.

== Description ==

Two post types, `book` (`/wp/v2/books`) and `author` (`/wp/v2/authors`; these are the
people who wrote the books, not WordPress users). A book can have several authors and an
author several books. The relation is stored in the `{prefix}acme_library_book_authors`
table, ordered by `position`.

Relation endpoints:

* `GET /wp-json/acme-library/v1/books/<id>/authors`
* `POST /wp-json/acme-library/v1/books/<id>/authors` with `{"authors": [ids]}`
* `GET /wp-json/acme-library/v1/authors/<id>/books`

Hooks:

* `acme_library_book_authors_updated` (action) `( $book_id, $new_ids, $old_ids )`,
  fired whenever the authors of a book change. The author book counters
  (`_acme_book_count` meta) and the newsletter plugin rely on it.

Template tags: `Acme\Library\get_book_authors( $book_id )`, `Acme\Library\book_byline( $book_id )`.

WP-CLI: `wp acme-library import <csv>`, `wp acme-library recount`.

== Changelog ==

= 2.3.1 =
* Fix: author book counters ignored unpublished books being published.

= 2.3.0 =
* Books list: Authors column. Authors list: Books column.

= 2.2.0 =
* `POST /acme-library/v1/books/<id>/authors`.

= 2.0.0 =
* Relations moved from the `_acme_author_ids` meta to a custom table (DB version 2).
  Existing data is migrated on update; the old meta is still read as a fallback.

= 1.4.0 =
* Old catalogue importer.
