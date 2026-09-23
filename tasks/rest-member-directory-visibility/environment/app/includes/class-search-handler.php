<?php
/**
 * /wp/v2/search?type=acme-member: find members by name or profile fields.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Member search handler.
 */
class Search_Handler extends \WP_REST_Search_Handler {

	const TYPE    = 'acme-member';
	const SUBTYPE = 'member';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->type     = self::TYPE;
		$this->subtypes = array( self::SUBTYPE );
	}

	/**
	 * Search.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public function search_items( \WP_REST_Request $request ) {
		$search   = strtolower( trim( (string) $request['search'] ) );
		$page     = max( 1, (int) $request['page'] );
		$per_page = max( 1, (int) $request['per_page'] );

		$found = array();
		foreach ( Members::all_ids() as $id ) {
			if ( '' === $search ) {
				$found[] = $id;
				continue;
			}
			$user     = get_userdata( $id );
			$haystack = strtolower( $user->display_name . ' ' . implode( ' ', Fields::get_all( $id ) ) );
			if ( false !== strpos( $haystack, $search ) ) {
				$found[] = $id;
			}
		}

		return array(
			self::RESULT_IDS   => array_slice( $found, ( $page - 1 ) * $per_page, $per_page ),
			self::RESULT_TOTAL => count( $found ),
		);
	}

	/**
	 * Prepare one result.
	 *
	 * @param int   $id     User ID.
	 * @param array $fields Requested fields.
	 * @return array
	 */
	public function prepare_item( $id, array $fields ) {
		$user = get_userdata( $id );
		$data = array();
		if ( in_array( \WP_REST_Search_Controller::PROP_ID, $fields, true ) ) {
			$data[ \WP_REST_Search_Controller::PROP_ID ] = (int) $id;
		}
		if ( in_array( \WP_REST_Search_Controller::PROP_TITLE, $fields, true ) ) {
			$data[ \WP_REST_Search_Controller::PROP_TITLE ] = $user ? $user->display_name : '';
		}
		if ( in_array( \WP_REST_Search_Controller::PROP_URL, $fields, true ) ) {
			$data[ \WP_REST_Search_Controller::PROP_URL ] = Members::profile_url( $id );
		}
		if ( in_array( \WP_REST_Search_Controller::PROP_TYPE, $fields, true ) ) {
			$data[ \WP_REST_Search_Controller::PROP_TYPE ] = self::TYPE;
		}
		if ( in_array( \WP_REST_Search_Controller::PROP_SUBTYPE, $fields, true ) ) {
			$data[ \WP_REST_Search_Controller::PROP_SUBTYPE ] = self::SUBTYPE;
		}
		return $data;
	}

	/**
	 * Links.
	 *
	 * @param int $id User ID.
	 * @return array
	 */
	public function prepare_item_links( $id ) {
		return array(
			'self' => array(
				'href'       => rest_url( 'wp/v2/users/' . (int) $id ),
				'embeddable' => true,
			),
		);
	}
}
