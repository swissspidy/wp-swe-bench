<?php
/**
 * Whose numbers: the whole site, or one author.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * A stats scope: 'site' or 'author:<id>'.
 */
final class Scope {

	/** @var int 0 for the whole site. */
	public $author_id = 0;

	/**
	 * Constructor.
	 *
	 * @param int $author_id Author ID, 0 for the site.
	 */
	private function __construct( $author_id ) {
		$this->author_id = (int) $author_id;
	}

	/**
	 * The whole site.
	 *
	 * @return Scope
	 */
	public static function site() {
		return new self( 0 );
	}

	/**
	 * One author.
	 *
	 * @param int $author_id Author.
	 * @return Scope
	 */
	public static function author( $author_id ) {
		return new self( max( 0, (int) $author_id ) );
	}

	/**
	 * What a user gets to see by default: editors and admins see the site, everybody else only themselves.
	 *
	 * @param int $user_id User.
	 * @return Scope
	 */
	public static function for_user( $user_id ) {
		return user_can( $user_id, 'edit_others_posts' ) ? self::site() : self::author( $user_id );
	}

	/**
	 * Whether a user may see this scope.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public function visible_to( $user_id ) {
		if ( ! $user_id || ! user_can( $user_id, 'edit_posts' ) ) {
			return false;
		}
		if ( user_can( $user_id, 'edit_others_posts' ) ) {
			return true;
		}
		return $this->author_id === (int) $user_id;
	}

	/**
	 * Whether this is the site scope.
	 *
	 * @return bool
	 */
	public function is_site() {
		return 0 === $this->author_id;
	}

	/**
	 * String form ('site' / 'author:3').
	 *
	 * @return string
	 */
	public function key() {
		return $this->is_site() ? 'site' : 'author:' . $this->author_id;
	}
}
