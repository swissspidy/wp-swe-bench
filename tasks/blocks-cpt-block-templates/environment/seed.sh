#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

wp rewrite flush >/dev/null

term() { # slug name
  wp term create acme_course_topic "$2" --slug="$1" --porcelain
}
term php "PHP" >/dev/null
term wordpress "WordPress" >/dev/null
term tools "Developer Tools" >/dev/null
term partner "Partner courses" >/dev/null
term design "Design" >/dev/null   # intentionally empty

course() { # slug title topics content-file date
  local id
  id=$(wp post create "seed/courses/$4" --post_type=acme_course --post_status=publish --post_name="$1" \
    --post_title="$2" --post_date="$5" --porcelain)
  wp post term set "$id" acme_course_topic $3 --by=slug >/dev/null
  echo "$id"
}

# 1.4+ format.
id=$(course intro-to-php "Introduction to PHP" "php" intro-to-php.html "2025-01-10 09:00:00")
wp post meta update "$id" _acme_course_price_cents 4900 >/dev/null
wp post meta update "$id" _acme_course_duration '{"value":6,"unit":"weeks"}' --format=json >/dev/null
wp post meta update "$id" _acme_course_status open >/dev/null
wp post update "$id" --post_excerpt="Learn PHP from scratch in six weeks." >/dev/null

# 1.0-era free-text meta that was never migrated.
id=$(course wordpress-basics "WordPress Basics" "wordpress" wordpress-basics.html "2025-02-01 09:00:00")
wp post meta update "$id" acme_course_price '$29' >/dev/null
wp post meta update "$id" acme_course_duration '3 wks' >/dev/null

id=$(course git-workshop "Git in 90 Minutes" "tools" git-workshop.html "2025-03-05 09:00:00")
wp post meta update "$id" _acme_course_price_cents 0 >/dev/null
wp post meta update "$id" _acme_course_duration '{"value":90,"unit":"minutes"}' --format=json >/dev/null

id=$(course advanced-php-patterns "Advanced PHP Patterns" "php" advanced-php.html "2025-04-12 09:00:00")
wp post meta update "$id" _acme_course_price_cents 12900 >/dev/null
wp post meta update "$id" _acme_course_duration '{"value":8,"unit":"weeks"}' --format=json >/dev/null
wp post meta update "$id" _acme_course_status closed >/dev/null

id=$(course block-themes-deep-dive "Block Themes Deep Dive" "wordpress design" block-themes.html "2025-05-20 09:00:00")
wp post meta update "$id" _acme_course_price_cents 7900 >/dev/null
wp post meta update "$id" _acme_course_duration '{"value":1,"unit":"weeks"}' --format=json >/dev/null
wp post meta update "$id" _acme_course_status waitlist >/dev/null
wp post meta update "$id" _acme_course_enroll_url 'https://academy.example.org/waitlist/block-themes' >/dev/null

id=$(course php-bootcamp "Enterprise PHP Bootcamp" "php partner" php-bootcamp.html "2025-06-02 09:00:00")
wp post meta update "$id" acme_course_price '1,299.50' >/dev/null
wp post meta update "$id" acme_course_duration '5 days' >/dev/null

id=$(course accessibility-coming-soon "Accessibility Fundamentals" "wordpress" accessibility.html "2025-07-15 09:00:00")
# No price and no duration yet.

id=$(wp post create seed/courses/draft.html --post_type=acme_course --post_status=draft --post_name=unreleased-course --post_title="Unreleased Course" --porcelain)
wp post meta update "$id" _acme_course_price_cents 9900 >/dev/null

# Imported by the old CSV importer with a dangerous enrollment link (must never become a javascript: link).
id=$(course imported-security-course "Web Security Basics" "tools" security.html "2025-08-01 09:00:00")
wp post meta update "$id" _acme_course_price_cents 1500 >/dev/null
wp post meta update "$id" _acme_course_enroll_url 'javascript:alert(document.cookie)' >/dev/null

# A regular blog post, to make sure nothing course-related leaks onto it.
wp post create --post_type=post --post_status=publish --post_name=welcome-to-the-academy \
  --post_title="Welcome to the Academy" --post_content='<!-- wp:paragraph --><p>Our new catalog is live.</p><!-- /wp:paragraph -->' >/dev/null

wp rewrite flush >/dev/null
