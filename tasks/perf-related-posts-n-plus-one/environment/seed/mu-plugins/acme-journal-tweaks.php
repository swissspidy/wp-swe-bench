<?php
/**
 * Plugin Name: Acme Journal tweaks
 * Description: Site-specific customisations of the Acme Journal (maintained by the web team).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sponsored content must never be recommended as related reading (legal, 2024-05),
 * except when an editor explicitly picked it.
 */
add_filter(
	'acme_related_post_ids',
	static function ( $ids, $post_id ) {
		$picked = Acme\Related\Engine::parse_manual( get_post_meta( $post_id, '_acme_related_manual', true ) );
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $picked ) {
					return in_array( (int) $id, $picked, true ) || ! has_category( 'sponsored', $id );
				}
			)
		);
	},
	10,
	2
);

/**
 * "Editor's pick" badge for posts flagged by the editors.
 */
add_filter(
	'acme_related_item_data',
	static function ( $data, $post ) {
		if ( get_post_meta( $post->ID, '_acme_editors_pick', true ) ) {
			$data['badge'] = __( "Editor's pick", 'acme-journal' );
		}
		return $data;
	},
	10,
	2
);
