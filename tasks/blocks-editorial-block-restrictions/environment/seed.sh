#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

wp user create contributor1 contributor1@acme.example --role=contributor --user_pass=password --display_name="Cory Contributor" >/dev/null
wp user create author1 author1@acme.example --role=author --user_pass=password --display_name="Ada Author" >/dev/null
wp user create editor1 editor1@acme.example --role=editor --user_pass=password --display_name="Eli Editor" >/dev/null
wp user create stringer1 stringer1@acme.example --role=contributor --user_pass=password --display_name="Sam Stringer" >/dev/null
wp user add-role stringer1 author >/dev/null

# Editorial rules as configured on production (Settings → Newsroom → Editorial rules).
wp eval '
update_option( "acme_newsroom_block_rules", array(
	"post_types" => array(
		"post" => array(
			"allowed" => null,
			"roles"   => array(
				"contributor" => array( "core/paragraph", "core/heading", "core/list", "core/quote", "core/image", "acme/*" ),
				"author"      => array( "core/paragraph", "core/heading", "core/list", "core/quote", "core/image", "core/gallery", "core/pullquote", "core/embed", "acme/*" ),
			),
		),
		"press_release" => array(
			"allowed" => array( "core/paragraph", "core/heading", "core/list", "core/image", "core/quote", "acme/*" ),
			"roles"   => array(
				"contributor" => array( "core/paragraph", "core/list" ),
			),
		),
	),
	"disabled_design_tools" => array(
		"contributor" => array( "custom_colors", "custom_font_sizes" ),
		"author"      => array( "custom_colors" ),
	),
) );'

wp post create seed/posts/press-release.html --user=admin --post_type=press_release --post_status=publish --post_name=q3-results --post_title="Acme reports record Q3" >/dev/null
wp post create seed/posts/old-press-release.html --user=admin --post_type=press_release --post_status=publish --post_name=new-headquarters --post_title="Acme opens new headquarters" --post_date="2021-03-01 09:00:00" >/dev/null
wp post create seed/posts/breaking-story.html --user=admin --post_type=post --post_status=publish --post_name=widget-recall --post_title="Widget recall" >/dev/null
wp post create seed/posts/contributor-draft.html --user=contributor1 --post_type=post --post_status=draft --post_name=contributor-draft --post_title="Widget pros and cons" >/dev/null
wp rewrite flush >/dev/null
