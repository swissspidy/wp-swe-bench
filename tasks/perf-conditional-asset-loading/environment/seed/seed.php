<?php
/**
 * Seeds Acme Outdoor: posts and pages with UI Kit blocks in all the places they can live.
 */

function seed_page( $slug, $title, $content, $type = 'page', $extra = array() ) {
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_author'  => 1,
			),
			$extra
		)
	);
	if ( is_wp_error( $id ) || ! $id ) {
		WP_CLI::error( "Could not create $slug" );
	}
	return $id;
}

$tabs = '<!-- wp:acme/tabs {"tabs":[{"title":"Gear","content":"Tents, packs and stoves."},{"title":"Trails","content":"Maps and routes for every season."},{"title":"Weather","content":"Check the forecast before you go."}]} /-->';
$acc  = '<!-- wp:acme/accordion {"items":[{"title":"How heavy is the tent?","content":"1.2 kg with poles."},{"title":"Is it waterproof?","content":"Yes, 3000 mm."}],"single":true} /-->';
$car  = '<!-- wp:acme/carousel {"slides":[{"caption":"Alps at dawn","color":"#d9e8f5"},{"caption":"Desert camp","color":"#f5e6d9"},{"caption":"Forest trail","color":"#dff5d9"}]} /-->';
$para = static function ( $text ) {
	return "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
};

// Regular content without any UI Kit component.
for ( $i = 1; $i <= 6; $i++ ) {
	seed_page( 'trail-report-' . $i, 'Trail report ' . $i, $para( 'We hiked a lovely trail. Report number ' . $i . '.' ) . "\n\n" . $para( 'Bring water.' ), 'post', array( 'post_date' => '2026-0' . min( 9, $i ) . '-01 10:00:00' ) );
}
seed_page( 'about', 'About us', $para( 'Acme Outdoor makes gear for people who like to be outside.' ) );

// Blocks in post content (nested in a group), one component per page.
seed_page( 'gear-guide', 'Gear guide', $para( 'Everything you need.' ) . "\n\n<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . $tabs . "</div>\n<!-- /wp:group -->" );
seed_page( 'gallery', 'Gallery', $para( 'Our favourite places.' ) . "\n\n" . $car );
seed_page( 'tent-faq', 'Tent FAQ', $acc );
seed_page( 'everything', 'Everything', $tabs . "\n\n" . $acc . "\n\n" . $car );

// Synced pattern with an accordion.
$pattern = seed_page( 'shipping-faq', 'Shipping FAQ', '<!-- wp:acme/accordion {"items":[{"title":"Shipping times?","content":"2-4 days."},{"title":"Returns?","content":"30 days, free."}]} /-->', 'wp_block' );
seed_page( 'shipping', 'Shipping', $para( 'How we ship.' ) . "\n\n<!-- wp:block {\"ref\":$pattern} /-->" );

// Template part with tabs, used by a custom page template (block theme).
$part = seed_page( 'acme-promo', 'Acme promo', '<!-- wp:acme/tabs {"tabs":[{"title":"Summer sale","content":"20% off tents."},{"title":"Members","content":"Free shipping."}],"className":"is-promo"} /-->', 'wp_template_part' );
wp_set_object_terms( $part, 'twentytwentyfive', 'wp_theme' );
wp_set_object_terms( $part, 'uncategorized', 'wp_template_part_area' );
$template = seed_page(
	'page-with-promo',
	'Page with promo',
	"<!-- wp:template-part {\"slug\":\"header\"} /-->\n\n<!-- wp:group {\"tagName\":\"main\",\"layout\":{\"type\":\"constrained\"}} -->\n<main class=\"wp-block-group\"><!-- wp:post-title /-->\n\n<!-- wp:post-content /-->\n\n<!-- wp:template-part {\"slug\":\"acme-promo\"} /--></main>\n<!-- /wp:group -->\n\n<!-- wp:template-part {\"slug\":\"footer\"} /-->",
	'wp_template'
);
wp_set_object_terms( $template, 'twentytwentyfive', 'wp_theme' );
update_post_meta( $template, 'is_wp_suggestion', false );
seed_page( 'summer', 'Summer', $para( 'Summer is here.' ), 'page', array( 'meta_input' => array( '_wp_page_template' => 'page-with-promo' ) ) );

// Classic content with the legacy shortcode.
seed_page( 'packing-list', 'Packing list', "Our classic packing list.\n\n[acme_tabs][acme_tab title=\"Day hike\"]Water, snacks, map.[/acme_tab][acme_tab title=\"Overnight\"]Tent, sleeping bag, stove.[/acme_tab][/acme_tabs]\n\nHappy trails!" );

// Other plugins using the runtime.
seed_page( 'newsletter', 'Newsletter', "<!-- wp:shortcode -->\n[acme_newsletter]\n<!-- /wp:shortcode -->" );
seed_page( 'help', 'Help', $para( 'Frequently asked:' ) . "\n\n<!-- wp:shortcode -->\n[acme_faq_teaser]\n<!-- /wp:shortcode -->" );

// Block widget for the classic theme's sidebar (used by the shop sub-site theme).
update_option(
	'widget_block',
	array(
		2              => array( 'content' => '<!-- wp:acme/accordion {"items":[{"title":"Store hours","content":"Mon-Sat 9-18"},{"title":"Contact","content":"shop@acme.example"}]} /-->' ),
		3              => array( 'content' => '<!-- wp:paragraph --><p>Free shipping over 50 EUR.</p><!-- /wp:paragraph -->' ),
		'_multiwidget' => 1,
	)
);

update_option(
	'sidebars_widgets',
	array(
		'wp_inactive_widgets' => array(),
		'sidebar-1'           => array( 'block-3', 'block-2' ),
		'array_version'       => 3,
	)
);

WP_CLI::log( 'Seeded Acme Outdoor.' );
