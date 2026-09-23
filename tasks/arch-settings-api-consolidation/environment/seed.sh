#!/usr/bin/env bash
# Native seeding: Acme Social 1.6.2 settings as they are stored on the production site
# (several historical formats), content, users.
set -euo pipefail
cd "$(dirname "$0")"
cp seed/mu-plugins/*.php /wordpress/wp-content/mu-plugins/

wp user create erin erin@example.org --role=editor --user_pass=password >/dev/null
wp user create alice alice@example.org --role=author --user_pass=password >/dev/null
wp user create sam sam@example.org --role=subscriber --user_pass=password >/dev/null

img=$(wp media import seed/acme-share.png --title="Acme share image" --porcelain)

wp post create --post_type=post --post_status=publish --post_name=launch-week --post_title="Launch week" \
  --post_content='<!-- wp:paragraph --><p>Our new widget line launches next week. Here is everything you need to know.</p><!-- /wp:paragraph -->' >/dev/null
memo=$(wp post create --post_type=post --post_status=publish --post_name=internal-memo --post_title="Internal memo" \
  --post_content='<!-- wp:paragraph --><p>Please do not share this one.</p><!-- /wp:paragraph -->' --porcelain)
wp post meta update "$memo" _acme_social_hide_buttons 1 >/dev/null
wp post create --post_type=page --post_status=publish --post_name=about-us --post_title="About us" \
  --post_content='<!-- wp:paragraph --><p>Follow us:</p><!-- /wp:paragraph --><!-- wp:shortcode -->[acme_social_profiles]<!-- /wp:shortcode -->' >/dev/null
wp post create --post_type=event --post_status=publish --post_name=community-meetup --post_title="Community meetup" \
  --post_content='<!-- wp:paragraph --><p>Meet the widget team in person.</p><!-- /wp:paragraph -->' >/dev/null

# Settings as stored by 1.0 – 1.6 over the years (the site was upgraded, never re-saved everything).
IMG_ID="$img" MEMO_ID="$memo" wp eval '
$img_url = wp_get_attachment_url( (int) getenv( "IMG_ID" ) );
update_option( "acme_social_share_buttons_enabled", "yes" );
update_option( "acme_social_networks", "Facebook, twitter,LinkedIn ,mastodon,myspace" );
update_option( "acme_share_position", "bottom" );
update_option( "acme_social_post_types", array( "post", "page", "event", "product" ) );
update_option( "acmesocial_button_style", "icons+text" );
update_option( "acme_social_twitter", "@AcmeHQ" );
update_option( "acme_social_facebook", "https://www.facebook.com/acmehq" );
update_option( "acme_social_instagram_url", "instagram.com/acmehq" );
update_option( "acme_social_linkedin", "" );
delete_option( "acme_social_youtube_channel" );
update_option( "acme_og_enabled", "1" );
update_option( "acme_og_default_image", $img_url );
update_option( "acme_social_fb_app_id", " 1234567890 " );
update_option( "acme_social_twitter_card", "large" );
update_option( "acme_social_version", "1.6.2" );
set_transient( "acme_social_og_" . (int) getenv( "MEMO_ID" ), "<!-- Acme Social -->\n<meta property=\"og:title\" content=\"Internal memo\" />\n", 12 * HOUR_IN_SECONDS );
'
wp rewrite flush >/dev/null
