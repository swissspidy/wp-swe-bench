=== Acme Courses ===
Contributors: acmeacademy
Tags: courses, lms, catalog
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Course catalog for Acme Academy: courses, topics, pricing, durations and enrollment links.

== Description ==

* Course post type (`acme_course`, archive at `/courses/`) and Topics taxonomy (`acme_course_topic`, `/course-topic/<slug>/`).
* Price (in cents, 0 = free), duration, enrollment status (open / waitlist / closed) and an optional enrollment URL per course.
* Settings → Courses: currency, enrollment page, course summary box.
* A summary box (price, duration, enroll button) above the description of every course.
* Templates for the course page, the catalog and topic archives. Themes can override them by
  shipping `single-acme_course.php`, `archive-acme_course.php`, `taxonomy-acme_course_topic.php`
  (or the same files in an `acme-courses/` folder), and `acme-courses/course-card.php`.
* Course List block.
* Block themes: Single Course, Course Catalog and Course Topic templates plus a Course Summary
  template part, all editable in the Site Editor (Appearance → Editor). Templates or parts with the same
  name in the theme take precedence; changes saved in the Site Editor take precedence over both.
* Blocks for templates: Course Price, Course Duration, Course Enroll Button.

= Template tags =

`acme_course_price()`, `acme_course_duration()`, `acme_course_enroll_button()`, `acme_course_topics()`.

= Filters =

* `acme_courses_price_html( $html, $cents, $course )`
* `acme_courses_duration_label( $label, $duration, $course )`
* `acme_courses_enroll_url( $url, $course )`
* `acme_courses_enroll_label( $label, $course )`
* `acme_courses_enroll_button_html( $html, $course )`
* `acme_courses_summary_html( $html, $course )`

== Changelog ==

= 1.7.0 =
* New: block theme support. Course pages use block templates (Single Course, Course Catalog, Course Topic)
  and a Course Summary template part that can be customized in the Site Editor.
* New: Course Price, Course Duration and Course Enroll Button blocks.
* Change: with block themes, the PHP templates and the summary box injected into the content are no longer
  used (classic themes are unchanged). New filter `acme_courses_inject_content`.

= 1.6.2 =
* Fix: waitlisted courses showed "Enroll now".
* Fix: legacy prices with thousands separators ("1,299.50") were parsed as 1.29.

= 1.6.0 =
* New: Course List block.
* New: `acme_courses_enroll_url` filter.

= 1.5.0 =
* New: enrollment status (open / waitlist / closed).
* Templates can be overridden from an `acme-courses/` theme folder.

= 1.4.0 =
* Prices are stored in cents (`_acme_course_price_cents`), durations as value + unit (`_acme_course_duration`).
  Old free-text values (`acme_course_price`, `acme_course_duration`) are still read.

= 1.0.0 =
* Initial release.
