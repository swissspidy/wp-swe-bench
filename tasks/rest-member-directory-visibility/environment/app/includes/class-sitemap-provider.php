<?php
/**
 * Sitemap provider: one URL per member profile page.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Member profiles sitemap.
 */
class Sitemap_Provider extends \WP_Sitemaps_Provider {

	const NAME     = 'acmemembers';
	const PER_PAGE = 500;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = self::NAME;
		$this->object_type = 'user';
	}

	/**
	 * Member IDs listed in the sitemap.
	 *
	 * @return int[]
	 */
	protected function member_ids() {
		return Members::all_ids();
	}

	/**
	 * URLs for one sitemap page.
	 *
	 * @param int    $page_num       Page.
	 * @param string $object_subtype Unused.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$ids  = array_slice( $this->member_ids(), ( max( 1, (int) $page_num ) - 1 ) * self::PER_PAGE, self::PER_PAGE );
		$urls = array();
		foreach ( $ids as $id ) {
			$urls[] = array( 'loc' => Members::profile_url( $id ) );
		}
		return $urls;
	}

	/**
	 * Number of sitemap pages.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return (int) ceil( count( $this->member_ids() ) / self::PER_PAGE );
	}
}
