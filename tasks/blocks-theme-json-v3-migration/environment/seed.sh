#!/usr/bin/env bash
# Native seeding (runs after the blueprint, at image build time).
set -euo pipefail
cd "$(dirname "$0")"

wp post create seed/posts/style-guide.html --post_type=post --post_status=publish --post_name=style-guide --post_title="Style guide" >/dev/null
wp post create seed/posts/about.html --post_type=page --post_status=publish --post_name=about --post_title="About the magazine" >/dev/null
wp post delete "$(wp post list --post_type=post --name=hello-world --field=ID)" --force >/dev/null 2>&1 || true

# Customizations made in the Site Editor over the years (stored as a v2 user theme.json).
wp eval '
$id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
// Created without a user (WP-CLI): the theme term must be set explicitly.
wp_set_object_terms( $id, get_stylesheet(), "wp_theme" );
wp_update_post( array(
	"ID"           => $id,
	"post_content" => wp_json_encode( array(
		"version"                     => 2,
		"isGlobalStylesUserThemeJSON" => true,
		"settings"                    => new stdClass(),
		"styles"                      => array(
			"blocks" => array(
				"core/site-title" => array( "typography" => array( "fontFamily" => "var:preset|font-family|serif" ) ),
				"core/button"     => array( "color" => array( "background" => "var:preset|color|accent" ) ),
			),
		),
	) ),
) );
'
