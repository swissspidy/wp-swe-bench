<?php
/**
 * Content fixtures for the Acme knowledge base (run with `wp eval-file`).
 *
 * Meta values are written as raw rows so that the stored bytes are exactly what
 * the production site has (JSON with escapes, Windows paths, regexes, serialized
 * arrays and objects).
 */

global $wpdb;

$fixtures = __DIR__ . '/fixtures/';

$raw_meta = static function ( $post_id, $key, $value ) use ( $wpdb ) {
	$wpdb->insert(
		$wpdb->postmeta,
		array(
			'post_id'    => $post_id,
			'meta_key'   => $key,
			'meta_value' => maybe_serialize( $value ),
		)
	);
};

$post = static function ( array $args, string $file ) use ( $fixtures ) {
	$args['post_content'] = file_get_contents( $fixtures . $file );
	$args                 = array_merge(
		array(
			'post_status' => 'publish',
			'post_author' => 1,
		),
		$args
	);
	$id = wp_insert_post( wp_slash( $args ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	return $id;
};

$term = static function ( $name, $taxonomy, $args = array() ) {
	$t = term_exists( $name, $taxonomy );
	if ( ! $t ) {
		$t = wp_insert_term( $name, $taxonomy, $args );
	}
	return (int) $t['term_id'];
};

// Categories and tags.
$guides = $term( 'Guides', 'category' );
$team   = $term( 'Team', 'category' );

// ---------------------------------------------------------------------------
// A guide with JSON, regexes, Windows paths and escaped block attributes.
// ---------------------------------------------------------------------------
$pricing = $post(
	array(
		'post_title'   => 'Pricing & validation cheatsheet',
		'post_name'    => 'pricing-cheatsheet',
		'post_excerpt' => 'Regexes like \d+ and paths like C:\Temp explained.',
		'post_date'    => '2025-11-03 09:15:00',
	),
	'pricing.html'
);
wp_set_object_terms( $pricing, array( $guides ), 'category' );
wp_set_object_terms( $pricing, array( 'regex', 'windows', 'billing' ), 'post_tag' );

$layout           = new stdClass();
$layout->template = 'two\\col';
$layout->json     = '{"title":"Caf\\u00e9"}';
$layout->columns  = 2;
$layout->sidebar  = array( 'widget' => 'search\\box' );

$raw_meta( $pricing, '_acme_pricing_json', '{"currency":"\\u20ac","plans":[{"name":"Starter \\"Solo\\"","pattern":"^\\\\d+$"},{"name":"Team \\u0026 Co"}]}' );
$raw_meta( $pricing, '_acme_export_path', 'C:\\Users\\Public\\Exports' );
$raw_meta( $pricing, '_acme_order_regex', '/^\\d{3}-[A-Z]\\w*$/i' );
$raw_meta(
	$pricing,
	'_acme_price_rows',
	array(
		array(
			'label'   => 'Starter "Solo"',
			'pattern' => '\\d+(\\.\\d{2})?',
			'html'    => '<span class=\\"price\\">€9</span>',
		),
		array(
			'label'   => 'Team',
			'share'   => '\\\\fileserver\\pricing',
			'amount'  => 29.5,
			'enabled' => true,
		),
	)
);
$raw_meta( $pricing, '_acme_layout', $layout );
$raw_meta( $pricing, '_acme_blocks_snapshot', '<!-- wp:paragraph {"placeholder":"\\u003cb\\u003eBold\\u003c/b\\u003e \\u0026 more"} --><p>Snapshot</p><!-- /wp:paragraph -->' );
$raw_meta( $pricing, '_acme_keywords', 'alpha\\beta' );
$raw_meta( $pricing, '_acme_keywords', 'gamma\\\\delta' );
$raw_meta( $pricing, '_acme_keywords', 'plain' );
$raw_meta( $pricing, '_acme_summary', 'Reviewed by the billing team.' );
$raw_meta( $pricing, '_acme_review_count', '42' );
$raw_meta( $pricing, '_acme_cache_rendered', '<p>stale cache</p>' );
$raw_meta( $pricing, '_acme_cache_etag', 'W/"abc"' );

// ---------------------------------------------------------------------------
// A plain post (no special characters anywhere).
// ---------------------------------------------------------------------------
$offsite = $post(
	array(
		'post_title'   => 'Team offsite notes',
		'post_name'    => 'team-offsite-notes',
		'post_excerpt' => 'What we did at the offsite.',
		'post_date'    => '2025-10-20 14:00:00',
	),
	'offsite.html'
);
wp_set_object_terms( $offsite, array( $team ), 'category' );
wp_set_object_terms( $offsite, array( 'offsite', 'planning' ), 'post_tag' );
$raw_meta( $offsite, '_acme_summary', 'Two days in the mountains.' );
$raw_meta( $offsite, '_acme_review_count', '7' );
$raw_meta(
	$offsite,
	'_acme_agenda',
	array(
		'morning'   => 'Roadmap',
		'afternoon' => 'Hiking',
		'days'      => 2,
	)
);

// ---------------------------------------------------------------------------
// A recipe (custom post type registered at the default priority).
// ---------------------------------------------------------------------------
$recipe = $post(
	array(
		'post_type'  => 'acme_recipe',
		'post_title' => 'Pesto alla genovese',
		'post_name'  => 'pesto',
	),
	'recipe.html'
);
wp_set_object_terms( $recipe, array( 'Italian', 'Vegetarian' ), 'cuisine' );
$raw_meta( $recipe, '_acme_servings', '4' );
$raw_meta( $recipe, '_acme_ingredients', array( 'basil', 'pine nuts', 'parmesan', 'olive oil' ) );

// ---------------------------------------------------------------------------
// Release notes (custom post type registered late on init).
// ---------------------------------------------------------------------------
$stable = $term( 'Stable', 'release_channel' );
$term( 'Beta', 'release_channel' );
$lts = $term( 'LTS', 'release_channel', array( 'parent' => $stable ) );
$term( 'Docs', 'component' );

$release = $post(
	array(
		'post_type'    => 'acme_release',
		'post_title'   => 'Acme 3.2',
		'post_name'    => 'acme-3-2',
		'post_excerpt' => 'Windows path fixes.',
		'post_date'    => '2025-12-01 08:00:00',
	),
	'release.html'
);
wp_set_object_terms( $release, array( $stable, $lts ), 'release_channel' );
wp_set_object_terms( $release, array( 'API', 'CLI' ), 'component' );
$raw_meta( $release, '_acme_changelog_json', '[{"type":"fix","text":"Paths like C:\\\\ProgramData\\\\Acme"},{"type":"fix","text":"Keep \\u00e9 escapes"}]' );
$raw_meta(
	$release,
	'_acme_downloads',
	array(
		'windows' => 'C:\\Program Files\\Acme\\acme.exe',
		'linux'   => '/usr/local/bin/acme',
	)
);
$raw_meta( $release, '_acme_version', '3.2.0' );

wp_cache_flush();

WP_CLI::log( sprintf( 'Seeded posts: pricing=%d offsite=%d recipe=%d release=%d', $pricing, $offsite, $recipe, $release ) );
