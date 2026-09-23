<?php
/**
 * The member directory: [acme_member_directory] shortcode and the "Member directory" block.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Directory rendering.
 */
class Directory {

	/**
	 * Cache lifetime of rendered directory pages (the directory page is our most visited page).
	 */
	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Fields shown on directory cards.
	 */
	const CARD_FIELDS = array( 'job_title', 'company', 'city', 'phone' );

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'acme_member_directory', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
		add_action( 'profile_update', array( __CLASS__, 'flush_cache' ) );
		add_action( 'set_user_role', array( __CLASS__, 'flush_cache' ) );
		add_action( 'deleted_user', array( __CLASS__, 'flush_cache' ) );
		add_action( 'acme_members_profile_saved', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Register the block (server-rendered; editor script without build step).
	 */
	public function register_block() {
		wp_register_script(
			'acme-member-directory-editor',
			ACME_MEMBERS_URL . 'blocks/member-directory/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			ACME_MEMBERS_VERSION,
			true
		);
		register_block_type(
			ACME_MEMBERS_DIR . 'blocks/member-directory',
			array( 'render_callback' => array( $this, 'render_block' ) )
		);
	}

	/**
	 * Front-end CSS.
	 */
	public function styles() {
		wp_register_style( 'acme-members', ACME_MEMBERS_URL . 'assets/members.css', array(), ACME_MEMBERS_VERSION );
		if ( is_singular() && ( has_shortcode( (string) get_post_field( 'post_content' ), 'acme_member_directory' ) || has_block( 'acme/member-directory' ) ) ) {
			wp_enqueue_style( 'acme-members' );
		}
	}

	/**
	 * Shortcode.
	 *
	 * @param array|string $atts Attributes: city, per_page.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'city'     => '',
				'per_page' => 12,
			),
			$atts,
			'acme_member_directory'
		);
		return $this->render( (string) $atts['city'], (int) $atts['per_page'] );
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$html = $this->render( isset( $attributes['city'] ) ? (string) $attributes['city'] : '', isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 12 );
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	/**
	 * Render the directory (cached).
	 *
	 * @param string $city     Only members from this city ('' = all).
	 * @param int    $per_page Cards per page.
	 * @return string
	 */
	public function render( $city, $per_page ) {
		$per_page = max( 1, min( 48, $per_page ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['members_page'] ) ? max( 1, absint( $_GET['members_page'] ) ) : 1;
		$viewer = get_current_user_id();

		// What a viewer sees depends on who they are: only the anonymous (public) view is shared
		// through the cache, logged-in views are rendered per request.
		$cache_key = $viewer ? '' : 'acme_members_dir_public_' . md5( wp_json_encode( array( strtolower( $city ), $per_page, $page ) ) );
		if ( $cache_key ) {
			$html = get_transient( $cache_key );
			if ( false !== $html ) {
				return $html;
			}
		}

		$members = array();
		foreach ( Members::all_ids() as $id ) {
			if ( ! Visibility::can_view_profile( $id, $viewer ) ) {
				continue;
			}
			if ( '' !== $city && ( ! Visibility::can_view_field( $id, 'city', $viewer ) || 0 !== strcasecmp( Fields::get( $id, 'city' ), $city ) ) ) {
				continue;
			}
			$members[] = $id;
		}

		$total = count( $members );
		$pages = (int) ceil( $total / $per_page );
		$slice = array_slice( $members, ( $page - 1 ) * $per_page, $per_page );
		$cards = array();
		foreach ( $slice as $id ) {
			$cards[] = array(
				'user'   => get_userdata( $id ),
				'values' => array_intersect_key( Visibility::visible_fields( $id, $viewer ), array_flip( self::CARD_FIELDS ) ),
			);
		}

		ob_start();
		include ACME_MEMBERS_DIR . 'templates/directory.php';
		$html = (string) ob_get_clean();

		if ( $cache_key ) {
			set_transient( $cache_key, $html, self::CACHE_TTL );
			self::remember_key( $cache_key );
		}
		return $html;
	}

	/**
	 * Track cache keys so they can be flushed.
	 *
	 * @param string $key Transient key.
	 */
	private static function remember_key( $key ) {
		$keys = get_option( 'acme_members_dir_cache_keys', array() );
		if ( ! in_array( $key, (array) $keys, true ) ) {
			$keys[] = $key;
			update_option( 'acme_members_dir_cache_keys', $keys, false );
		}
	}

	/**
	 * Flush all cached directory pages.
	 */
	public static function flush_cache() {
		foreach ( (array) get_option( 'acme_members_dir_cache_keys', array() ) as $key ) {
			delete_transient( $key );
		}
		delete_option( 'acme_members_dir_cache_keys' );
	}
}
