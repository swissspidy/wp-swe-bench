#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/
cp -R seed/themes/acme-classic /wordpress/wp-content/themes/
wp theme activate acme-classic >/dev/null

# Event categories.
cat_id() { wp term create acme_event_category "$1" --slug="$2" --porcelain; }
WORKSHOPS=$(cat_id "Workshops" workshops)
MEETUPS=$(cat_id "Meetups" meetups)
CONFERENCES=$(cat_id "Conferences" conferences)

# Events: title | slug | start | end | venue | categories | status | extra meta
event() {
  local id
  id=$(wp post create --post_type=acme_event --post_status="$7" --post_title="$1" --post_name="$2" \
      --post_content="<!-- wp:acme/event-details /-->" --porcelain)
  wp post meta update "$id" _acme_event_start "$3" >/dev/null
  [ -n "$4" ] && wp post meta update "$id" _acme_event_end "$4" >/dev/null
  wp post meta update "$id" _acme_event_venue "$5" >/dev/null
  wp post term set "$id" acme_event_category $6 --by=id >/dev/null
  echo "$id"
}
event "Intro to Gutenberg" intro-to-gutenberg "2031-03-10 18:00" "2031-03-10 20:00" "Acme HQ, Room 1" "$WORKSHOPS" publish >/dev/null
event "WordPress Meetup Zürich" meetup-zurich "2031-04-02 19:00" "" 'Café "Zürich" & Co' "$MEETUPS" publish >/dev/null
event "Block Themes Deep Dive" block-themes-deep-dive "2031-05-15 09:30" "2031-05-15 16:00" "Online" "$WORKSHOPS" publish >/dev/null
event "WordCamp Acme 2031" wordcamp-acme-2031 "2031-06-20 09:00" "2031-06-21 17:00" "Convention Center" "$CONFERENCES" publish >/dev/null
CANCELLED=$(event "Summer Social" summer-social "2031-07-01 18:00" "" "Rooftop Bar" "$MEETUPS" publish)
wp post meta update "$CANCELLED" _acme_event_status cancelled >/dev/null
event "Performance Clinic" performance-clinic "2031-08-12 17:00" "" "" "$WORKSHOPS $MEETUPS" publish >/dev/null
MEMBERS=$(event "Accessibility Workshop" accessibility-workshop "2031-09-09 10:00" "" "Acme HQ, Room 2" "$WORKSHOPS" publish)
wp post meta update "$MEMBERS" _acme_members_only 1 >/dev/null
event "Holiday Meetup" holiday-meetup "2031-12-12 18:30" "" "Acme HQ, Lounge" "$MEETUPS" publish >/dev/null
event "Launch Party" launch-party "2021-05-01 20:00" "" "Acme HQ" "$MEETUPS" publish >/dev/null
event "Old Workshop" old-workshop "2020-11-11 10:00" "" "Acme HQ, Room 1" "$WORKSHOPS" publish >/dev/null
event "Secret Planning Session" secret-planning "2031-02-01 10:00" "" "Board room" "$WORKSHOPS" draft >/dev/null

# Content using the shortcode.
mk() { # slug type title file [status]
  wp post create "seed/posts/$4" --post_type="$2" --post_status="${5:-publish}" --post_name="$1" --post_title="$3" --porcelain
}
WHATS_ON=$(mk whats-on page "What's on" whats-on-v1.html)
# Edited later: the first version stays in the revision history.
wp post update "$WHATS_ON" seed/posts/whats-on.html >/dev/null
mk meetups-roundup post "Meetups roundup" meetups-roundup.html >/dev/null
mk inline-shortcode post "Inline shortcode" inline-shortcode.html >/dev/null
mk nested-shortcodes post "Talks and workshops" nested-shortcodes.html >/dev/null
mk escaped-shortcode post "How to list events" escaped-shortcode.html >/dev/null
mk draft-events post "Past events recap" draft-events.html draft >/dev/null
mk no-events page "About us" no-events.html >/dev/null
SYNCED=$(mk sidebar-events wp_block "Soon at Acme" synced-events.html)
printf '<!-- wp:paragraph -->\n<p>Conferences:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:block {"ref":%s} /-->\n' "$SYNCED" > /tmp/uses-synced.html
wp post create /tmp/uses-synced.html --post_type=post --post_status=publish --post_name=conferences --post_title="Conferences" --porcelain >/dev/null
rm -f /tmp/uses-synced.html

# Widgets (block widgets are enabled on this site; the events widgets are classic ones).
wp option update widget_block --format=json '{"2":{"content":"<!-- wp:paragraph -->\n<p>Welcome to the Acme site.</p>\n<!-- /wp:paragraph -->"},"3":{"content":"<!-- wp:paragraph -->\n<p>© Acme Inc.</p>\n<!-- /wp:paragraph -->"},"_multiwidget":1}' >/dev/null
wp option update widget_acme_upcoming_events --format=json "{\"2\":{\"title\":\"Workshops\",\"count\":3,\"category\":$WORKSHOPS,\"show_venue\":true},\"3\":{\"title\":\"Meetups & \\\"Talks\\\"\",\"count\":10,\"category\":0,\"show_venue\":false},\"4\":{\"title\":\"\",\"count\":2,\"category\":99999,\"show_venue\":true},\"_multiwidget\":1}" >/dev/null
wp option update sidebars_widgets --format=json '{"wp_inactive_widgets":["acme_upcoming_events-4"],"sidebar-1":["block-2","acme_upcoming_events-2"],"footer-1":["acme_upcoming_events-3","block-3"],"array_version":3}' >/dev/null
wp option get widget_acme_upcoming_events --format=json
wp rewrite flush >/dev/null
