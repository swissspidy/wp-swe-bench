<?php
/**
 * Content of the Acme developer site (run with `wp eval-file`).
 */

global $wpdb;

// Fresh installs have a draft privacy policy page; not needed here.
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}

$term = static function ( $name, $slug, $taxonomy ) {
	$t = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $t ) ) {
		WP_CLI::error( $t );
	}
	return (int) $t['term_id'];
};

$cloud = $term( 'Acme Cloud', 'acme-cloud', 'product' );
$cli   = $term( 'Acme CLI', 'acme-cli', 'product' );
$v1    = $term( 'v1', 'v1', 'doc_version' );
$v2    = $term( 'v2', 'v2', 'doc_version' );

/**
 * Creates a doc with an exact slug (duplicates across products/versions are intended).
 */
$doc = static function ( $title, $slug, $product, $parent = 0, $args = array() ) use ( $wpdb ) {
	$version = $args['version'] ?? 0;
	unset( $args['version'] );
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'    => 'doc',
				'post_status'  => 'publish',
				'post_author'  => 1,
				'post_title'   => $title,
				'post_parent'  => $parent,
				'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $title ) . " documentation.</p>\n<!-- /wp:paragraph -->",
			),
			$args
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	wp_set_object_terms( $id, array( $product ), 'product' );
	if ( $version ) {
		wp_set_object_terms( $id, array( $version ), 'doc_version' );
	}
	if ( null !== $slug ) {
		$wpdb->update( $wpdb->posts, array( 'post_name' => $slug ), array( 'ID' => $id ) );
	}
	clean_post_cache( $id );
	return $id;
};

// Acme Cloud (current docs).
$c_start = $doc( 'Getting started', 'getting-started', $cloud, 0, array( 'menu_order' => 1 ) );
$doc( 'Installation', 'installation', $cloud, $c_start, array( 'menu_order' => 1, 'post_content' => "<!-- wp:paragraph -->\n<p>Install the Acme Cloud agent.</p>\n<!-- /wp:paragraph -->" ) );
$doc( 'Configuration', 'configuration', $cloud, $c_start, array( 'menu_order' => 2 ) );
$doc( 'Billing', 'billing', $cloud, 0, array( 'menu_order' => 2 ) );
$c_api = $doc( 'API reference', 'api-reference', $cloud, 0, array( 'menu_order' => 3 ) );
$doc(
	'Authentication',
	'authentication',
	$cloud,
	$c_api,
	array(
		'post_content' => "<!-- wp:paragraph -->\n<p>Tokens: part one.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->\n\n<!-- wp:paragraph -->\n<p>OAuth flows: part two.</p>\n<!-- /wp:paragraph -->",
	)
);

// Acme Cloud v2 (next major version).
$c2_start = $doc( 'Getting started', 'getting-started', $cloud, 0, array( 'version' => $v2, 'menu_order' => 1 ) );
$doc( 'Installation', 'installation', $cloud, $c2_start, array( 'version' => $v2, 'post_content' => "<!-- wp:paragraph -->\n<p>Install the Acme Cloud v2 agent.</p>\n<!-- /wp:paragraph -->" ) );

// Acme CLI.
$l_start = $doc( 'Getting started', 'getting-started', $cli, 0, array( 'menu_order' => 1 ) );
$doc( 'Installation', 'installation', $cli, $l_start, array( 'post_content' => "<!-- wp:paragraph -->\n<p>Install the CLI with <code>npm i -g acme</code>. See also [acme_doc_link product=\"acme-cloud\" path=\"getting-started/installation\"]the Cloud agent[/acme_doc_link].</p>\n<!-- /wp:paragraph -->" ) );
$l_cmd = $doc( 'Commands', 'commands', $cli, 0, array( 'menu_order' => 2 ) );
$doc( 'deploy', 'deploy', $cli, $l_cmd );
$doc( 'CLI v1 getting started', 'getting-started', $cli, 0, array( 'version' => $v1 ) );

// Unpublished docs.
$doc( 'Roadmap', null, $cloud, 0, array( 'post_status' => 'draft', 'post_content' => "<!-- wp:paragraph -->\n<p>Secret roadmap draft.</p>\n<!-- /wp:paragraph -->" ) );
$doc( 'Webhooks', 'webhooks', $cloud, $c_api, array( 'post_status' => 'pending', 'post_content' => "<!-- wp:paragraph -->\n<p>Webhooks pending review.</p>\n<!-- /wp:paragraph -->" ) );

// Pages: the new docs landing page and its sub-pages, and an about page.
$page = static function ( $title, $slug, $parent = 0 ) {
	return wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_parent'  => $parent,
			'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $title ) . " page.</p>\n<!-- /wp:paragraph -->",
		)
	);
};
$landing = $page( 'Docs', 'docs' );
$page( 'Contributing to the docs', 'contributing', $landing );
$page( 'Style guide', 'style-guide', $landing );
$page( 'About', 'about' );

wp_cache_flush();
WP_CLI::log( 'Seeded docs.' );
