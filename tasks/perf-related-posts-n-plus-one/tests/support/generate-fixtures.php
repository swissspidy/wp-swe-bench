<?php
/**
 * Generates the parity fixtures from the STARTING implementation (Acme Related 2.3.1):
 *   wp eval-file generate-fixtures.php > fixtures/parity.json
 * Run on a freshly provisioned image (pristine DB) with the starting plugin code.
 */

global $wpdb;

function wpsb_seed_post_id( $n ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_title LIKE %s", '% (' . $n . ')' ) );
}

$numbers = array_merge( range( 3, 200, 7 ), array( 1, 2, 5, 6, 11, 23, 42, 50, 64, 88, 117, 150, 199, 200 ) );
$numbers = array_values( array_unique( $numbers ) );
sort( $numbers );

$out = array( 'sources' => array() );
foreach ( $numbers as $n ) {
	$id = wpsb_seed_post_id( $n );
	ob_start();
	acme_related_the_list( $id );
	$html     = ob_get_clean();
	$request  = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $id );
	$request->set_query_params( array( 'context' => 'view' ) );
	$response = rest_do_request( $request );
	$data     = $response->get_data();
	$out['sources'][ $n ] = array(
		'id'         => $id,
		'ids'        => acme_related_get_ids( $id ),
		'ids_12'     => acme_related_get_ids( $id, 12 ),
		'ids_1'      => acme_related_get_ids( $id, 1 ),
		'html'       => $html,
		'html_6'     => ( function () use ( $id ) {
			ob_start();
			acme_related_the_list( $id, array( 'count' => 6, 'heading' => 'Six more' ) );
			return ob_get_clean();
		} )(),
		'rest_field' => $data['acme_related'],
		'block'      => do_blocks( sprintf( '<!-- wp:acme/related-posts {"postId":%d,"count":5,"heading":"Block heading","className":"is-style-compact"} /-->', $id ) ),
	);
	wp_cache_flush();
}
echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
