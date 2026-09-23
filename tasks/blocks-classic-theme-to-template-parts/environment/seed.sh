#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

drafts=$(wp post list --post_type=page --post_status=draft --format=ids)
if [ -n "$drafts" ]; then wp post delete $drafts --force >/dev/null; fi

page() { # slug title file [parent] [menu_order]
  wp post create "seed/pages/$3" --post_type=page --post_status=publish --post_name="$1" --post_title="$2" \
    --post_parent="${4:-0}" --menu_order="${5:-0}" --porcelain
}
home=$(page home "Home" home.html 0 1)
about=$(page about "About us" about.html 0 2)
team=$(page team "Our team" simple.html "$about" 1)
services=$(page services "Services" simple.html 0 3)
contact=$(page contact "Contact" simple.html 0 5)
privacy=$(page privacy-policy "Privacy Policy" simple.html 0 9)
news=$(page news "News" simple.html 0 4)
wp post delete "$(wp post list --post_type=page --name=sample-page --field=ID)" --force >/dev/null 2>&1 || true

wp option update show_on_front page >/dev/null
wp option update page_on_front "$home" >/dev/null
wp option update page_for_posts "$news" >/dev/null

wp post create --post_type=post --post_status=publish --post_name=new-plant-opens --post_title="Our new plant opens in Springfield" \
  --post_content='<!-- wp:paragraph --><p>The new plant will create 200 jobs.</p><!-- /wp:paragraph -->' >/dev/null

# Menus.
main=$(wp menu create "Main menu" --porcelain)
wp menu item add-custom "$main" "Home" "http://127.0.0.1:9400/" >/dev/null
about_item=$(wp menu item add-post "$main" "$about" --title="About" --porcelain)
wp menu item add-post "$main" "$team" --title="Team" --parent-id="$about_item" >/dev/null
wp menu item add-post "$main" "$services" >/dev/null
wp menu item add-custom "$main" "Careers" "https://jobs.acme-corp.example/" >/dev/null
wp menu item add-post "$main" "$contact" >/dev/null
wp menu location assign "$main" primary

footer=$(wp menu create "Footer links" --porcelain)
wp menu item add-post "$footer" "$privacy" >/dev/null
wp menu item add-custom "$footer" "Imprint" "http://127.0.0.1:9400/imprint/" >/dev/null
wp menu item add-post "$footer" "$contact" --title="Get in touch" >/dev/null
wp menu location assign "$footer" footer

# Customizer settings (3.x) plus leftovers from the 2.x theme.
wp eval '
set_theme_mod( "acme_corporate_show_tagline", true );
set_theme_mod( "acme_corporate_header_cta_label", "Request a quote" );
set_theme_mod( "acme_corporate_header_cta_url", "/contact/" );
set_theme_mod( "acme_corporate_footer_text", "&copy; {year} {site}. All rights reserved. <a href=\"/privacy-policy/\">Privacy</a>" );
set_theme_mod( "acme_corporate_social_links", array( "linkedin" => "https://www.linkedin.com/company/acme-corp", "twitter" => "", "github" => "https://github.com/acme-corp", "youtube" => "", "facebook" => "" ) );
set_theme_mod( "acme_corporate_twitter_url", "https://twitter.com/acmecorp" );
set_theme_mod( "acme_corporate_youtube_url", "javascript:alert(document.domain)" );
set_theme_mod( "acme_corporate_contact_phone", "+1 (555) 010-2030" );
set_theme_mod( "acme_corporate_contact_email", "hello@acme-corp.example" );
'

# Footer widget area: the office address (classic text widget).
wp widget reset --all >/dev/null 2>&1 || true
wp widget add text footer-1 1 --title="Head office" --text="Acme Corporation<br>221 Example Street<br>Springfield, OR 97477" >/dev/null
wp widget add search sidebar-1 1 --title="Search" >/dev/null
