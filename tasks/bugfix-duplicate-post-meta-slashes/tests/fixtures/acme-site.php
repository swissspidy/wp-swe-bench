<?php
/**
 * Plugin Name: Acme Site (content model)
 * Description: Content types, taxonomies and blocks of the Acme knowledge base.
 * Author:      Acme Web Team
 * Version:     2.3.0
 *
 * @package Acme\Site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Recipes (team cookbook) and their cuisines.
 */
function acme_site_register_recipes() {
	register_taxonomy(
		'cuisine',
		'acme_recipe',
		array(
			'label'        => __( 'Cuisines', 'acme-site' ),
			'hierarchical' => false,
			'show_in_rest' => true,
			'rewrite'      => array( 'slug' => 'cuisine' ),
		)
	);

	register_post_type(
		'acme_recipe',
		array(
			'label'        => __( 'Recipes', 'acme-site' ),
			'public'       => true,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions' ),
			'taxonomies'   => array( 'cuisine' ),
			'rewrite'      => array( 'slug' => 'recipes' ),
		)
	);
}
add_action( 'init', 'acme_site_register_recipes' );

/**
 * Release notes.
 *
 * Registered after the product catalogue (which registers on init at the default priority)
 * so that the product list can be read from its settings.
 */
function acme_site_register_releases() {
	register_post_type(
		'acme_release',
		array(
			'label'        => __( 'Release notes', 'acme-site' ),
			'public'       => true,
			'show_in_rest' => true,
			'rest_base'    => 'releases',
			'supports'     => array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions', 'author' ),
			'rewrite'      => array( 'slug' => 'releases' ),
		)
	);

	register_taxonomy(
		'release_channel',
		'acme_release',
		array(
			'label'        => __( 'Channels', 'acme-site' ),
			'hierarchical' => true,
			'show_in_rest' => true,
			'rest_base'    => 'release-channels',
		)
	);

	register_taxonomy(
		'component',
		'acme_release',
		array(
			'label'        => __( 'Components', 'acme-site' ),
			'hierarchical' => false,
			'show_in_rest' => true,
			'rest_base'    => 'components',
		)
	);
}
add_action( 'init', 'acme_site_register_releases', 20 );

/**
 * The price table block (server rendered, attributes only).
 */
function acme_site_register_blocks() {
	register_block_type(
		'acme/price-table',
		array(
			'api_version'     => 3,
			'attributes'      => array(
				'rows'     => array(
					'type'    => 'array',
					'default' => array(),
				),
				'currency' => array(
					'type'    => 'string',
					'default' => '$',
				),
			),
			'render_callback' => 'acme_site_render_price_table',
		)
	);
}
add_action( 'init', 'acme_site_register_blocks' );

/**
 * Renders the price table block.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function acme_site_render_price_table( $attributes ) {
	$rows = '';
	foreach ( (array) $attributes['rows'] as $row ) {
		$rows .= sprintf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html( $row['label'] ?? '' ),
			wp_kses_post( $row['note'] ?? '' )
		);
	}
	return sprintf(
		'<table class="acme-price-table" data-currency="%s"><tbody>%s</tbody></table>',
		esc_attr( $attributes['currency'] ),
		$rows
	);
}
