<?php
/**
 * Member profile pages: /members/{nicename}/.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Profile page routing + template.
 */
class Profile_Page {

	const QUERY_VAR = 'acme_member';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_filter( 'document_title_parts', array( $this, 'title' ) );
	}

	/**
	 * Rewrite rule for profile pages.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^members/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Register the query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * The member being shown, if the viewer may see them.
	 *
	 * @return \WP_User|null
	 */
	private function current_member() {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			return null;
		}
		$user = Members::by_slug( sanitize_title( $slug ) );
		if ( ! $user || ! Visibility::can_view_profile( $user->ID, get_current_user_id() ) ) {
			return null;
		}
		return $user;
	}

	/**
	 * Render the profile page (or a 404 for unknown/hidden members).
	 */
	public function maybe_render() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		$member = $this->current_member();
		if ( ! $member ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		status_header( 200 );
		$fields = Fields::all();
		$values = Visibility::visible_fields( $member->ID, get_current_user_id() );
		get_header();
		include ACME_MEMBERS_DIR . 'templates/profile.php';
		get_footer();
		exit;
	}

	/**
	 * Document title for profile pages.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function title( $parts ) {
		$member = get_query_var( self::QUERY_VAR ) ? $this->current_member() : null;
		if ( $member ) {
			$parts['title'] = $member->display_name;
		}
		return $parts;
	}
}
