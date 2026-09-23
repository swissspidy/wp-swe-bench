<?php
/**
 * Admin menu.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Acme Social" admin menu and its three screens.
 */
class Acme_Social_Admin {

	/**
	 * Screen objects.
	 *
	 * @var array
	 */
	private $pages = array();

	/**
	 * Hooks.
	 */
	public function register() {
		$this->pages = array(
			'sharing'  => new Acme_Social_Sharing_Page(),
			'profiles' => new Acme_Social_Profiles_Page(),
			'og'       => new Acme_Social_Open_Graph_Page(),
		);
		foreach ( $this->pages as $page ) {
			$page->register();
		}
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ACME_SOCIAL_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Capability required to manage the plugin settings.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required to see and change the Acme Social settings.
		 *
		 * @since 1.4.0
		 *
		 * @param string $capability Capability. Default 'manage_options'.
		 */
		return (string) apply_filters( 'acme_social_admin_capability', 'manage_options' );
	}

	/**
	 * Menu entries.
	 */
	public function menu() {
		$cap = self::capability();
		add_menu_page(
			__( 'Acme Social', 'acme-social' ),
			__( 'Acme Social', 'acme-social' ),
			$cap,
			'acme-social',
			array( $this->pages['sharing'], 'render' ),
			'dashicons-share',
			81
		);
		$hooks = array(
			'sharing'  => add_submenu_page( 'acme-social', __( 'Sharing', 'acme-social' ), __( 'Sharing', 'acme-social' ), $cap, 'acme-social', array( $this->pages['sharing'], 'render' ) ),
			'profiles' => add_submenu_page( 'acme-social', __( 'Social profiles', 'acme-social' ), __( 'Profiles', 'acme-social' ), $cap, 'acme-social-profiles', array( $this->pages['profiles'], 'render' ) ),
			'og'       => add_submenu_page( 'acme-social', __( 'Open Graph', 'acme-social' ), __( 'Open Graph', 'acme-social' ), $cap, 'acme-social-og', array( $this->pages['og'], 'render' ) ),
		);
		foreach ( $hooks as $key => $hook ) {
			if ( $hook && method_exists( $this->pages[ $key ], 'load' ) ) {
				add_action( 'load-' . $hook, array( $this->pages[ $key ], 'load' ) );
			}
		}
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=acme-social' ) ), esc_html__( 'Settings', 'acme-social' ) ) );
		return $links;
	}
}
