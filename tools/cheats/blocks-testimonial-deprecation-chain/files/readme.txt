=== Acme Testimonials ===
Contributors: acmeweb
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later

Customer testimonial block with ratings, author photos, review structured data and a CSV importer.

== Description ==

* Block "Testimonial" (`acme/testimonial`): quote, author name, author role, rating, author photo. Styles: Default, Card. Alignment: left, right, wide.
* Review structured data (JSON-LD, schema.org `Review`) is printed in the page `<head>` for every testimonial. Filter: `acme_testimonials_schema_review`.
* WP-CLI: `wp acme-testimonials import <file.csv>` creates a page from a CSV export of our review tool (columns `quote`, `name`, `role`, `rating`, `avatar_id`); `wp acme-testimonials list <post_id>`.

== Changelog ==

= 4.0.0 =
* Half-star ratings (0–5, 0 = not rated), rendered as star elements with an accessible label ("Rated 4.5 out of 5").
* The author name is a `<cite>`; the photo sits in the byline, is decorative and uses the thumbnail size. "Remove avatar" button.
* Every saved format (1.x, 2.x, 3.x and 3.2 CSV imports) opens without block errors and is upgraded: 1.x "Name, Role" authors are split at the first comma, string ratings become numbers.
* The CSV importer writes 4.x markup; structured data understands 4.x markup and half stars.

= 3.2.0 =
* WP-CLI importer for CSV exports of the review tool.
* `wp acme-testimonials list`.

= 3.1.0 =
* "Card" block style.

= 3.0.0 =
* Ratings are stored as numbers.
* Author photo from the media library.
* `has-rating` class on rated testimonials, accessible label on the stars.

= 2.1.0 =
* Review structured data (JSON-LD).

= 2.0.0 =
* The author field is split into name and role. Existing testimonials keep working.
* Star rating (1–5).
* Left/right/wide alignment.

= 1.0.0 =
* Initial release: quote + author.
