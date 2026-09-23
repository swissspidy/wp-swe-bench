#!/usr/bin/env bash
# Native seeding: the Acme Daily newsroom as it is today (roles customized by the site owner,
# freelancers flagged with user meta in both historical formats, stories in every state).
set -euo pipefail
cd "$(dirname "$0")"

# Role customizations made by the site owner over the years (with a role editor plugin).
wp eval '
$author = get_role( "author" );
$author->remove_cap( "publish_posts" );          // Authors submit, the desk publishes.
get_role( "editor" )->add_cap( "manage_newsletter" );
add_role( "section_editor", "Section Editor", array(
	"read" => true, "upload_files" => true, "edit_posts" => true, "edit_others_posts" => true,
	"edit_published_posts" => true, "publish_posts" => true, "moderate_comments" => true,
) );
add_role( "newsletter_manager", "Newsletter Manager", array( "read" => true, "manage_newsletter" => true ) );
'

u() { # login role display-name
  wp user create "$1" "$1@acme-daily.example" --role="$2" --display_name="$3" --user_pass=password --porcelain
}
erin=$(u erin editor "Erin Editor")
sven=$(u sven section_editor "Sven Section")
alice=$(u alice author "Alice Author")
carl=$(u carl contributor "Carl Contributor")
fiona=$(u fiona contributor "Fiona Freelance")
frank=$(u frank contributor "Frank Freelance")
fay=$(u fay contributor "Fay Freelance")
felix=$(u felix author "Felix Former-Freelancer")
nora=$(u nora contributor "Nora Newbie")
u sam subscriber "Sam Subscriber" >/dev/null

wp user add-role "$fay" newsletter_manager
wp user meta update "$fiona" acme_freelancer 1 >/dev/null
wp user meta update "$frank" acme_freelancer yes >/dev/null   # flagged by 1.x
wp user meta update "$fay" acme_freelancer 1 >/dev/null
wp user meta update "$felix" acme_freelancer 1 >/dev/null     # was a freelancer, hired as staff author
wp user meta update "$nora" acme_freelancer 0 >/dev/null

city=$(wp term create desk City --porcelain)
wp term create desk Politics --porcelain >/dev/null
wp term create desk Sports --porcelain >/dev/null

s() { # slug status author title
  wp post create --post_type=story --post_status="$2" --post_author="$3" --post_name="$1" --post_title="$4" \
    --post_content="<!-- wp:paragraph --><p>$4: full report.</p><!-- /wp:paragraph -->" --porcelain
}
approve() { # story approver value
  wp post meta update "$1" _acme_approved "$3" >/dev/null
  wp post meta update "$1" _acme_approved_by "$2" >/dev/null
  wp post meta update "$1" _acme_approved_at "2025-11-03 09:15:00" >/dev/null
}
s harbor-fire draft "$fiona" "Harbor fire" >/dev/null
budget=$(s city-budget pending "$fiona" "City budget")
approve "$budget" "$erin" 1
ferry=$(s ferry-strike publish "$frank" "Ferry strike")
approve "$ferry" "$erin" yes
old=$(s tram-extension draft "$frank" "Tram extension")
approve "$old" "$sven" yes
s council-vote publish "$erin" "Council vote" >/dev/null
s editors-note draft "$erin" "Editor's note" >/dev/null
s fays-column draft "$fay" "Fay's column" >/dev/null
s carl-draft draft "$carl" "Carl's draft" >/dev/null
s felix-feature draft "$felix" "Felix feature" >/dev/null
for id in $(wp post list --post_type=story --post_status=any --format=ids); do wp post term set "$id" desk "$city" --by=id >/dev/null; done

wp post create --post_type=post --post_status=publish --post_title="Welcome to Acme Daily" --post_author="$erin" >/dev/null
wp rewrite flush >/dev/null
