<?php
/**
 * Admin menu and assets.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

use Acme\Forms\Installer;
use Acme\Forms\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Acme Forms" admin menu.
 */
class Admin {

	const MENU_SLUG = 'acme-forms-submissions';

	/**
	 * Hook suffixes of our screens.
	 *
	 * @var string[]
	 */
	protected $hooks = array();

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ACME_FORMS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Menu entries.
	 */
	public function menu() {
		$inbox    = Plugin::instance()->get( 'inbox' );
		$settings = Plugin::instance()->get( 'settings' );

		$this->hooks['inbox'] = add_menu_page(
			__( 'Submissions', 'acme-forms' ),
			__( 'Acme Forms', 'acme-forms' ),
			Installer::CAP,
			self::MENU_SLUG,
			array( $inbox, 'render' ),
			'dashicons-feedback',
			26
		);
		add_submenu_page( self::MENU_SLUG, __( 'Submissions', 'acme-forms' ), __( 'Submissions', 'acme-forms' ), Installer::CAP, self::MENU_SLUG, array( $inbox, 'render' ) );
		$this->hooks['settings'] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Acme Forms Settings', 'acme-forms' ),
			__( 'Settings', 'acme-forms' ),
			'manage_options',
			Settings_Page::SLUG,
			array( $settings, 'render' )
		);

		$this->hooks['audit'] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Audit log', 'acme-forms' ),
			__( 'Audit log', 'acme-forms' ),
			'manage_options',
			Audit_Log_Page::SLUG,
			array( Plugin::instance()->get( 'audit_log' ), 'render' )
		);

		add_action( 'load-' . $this->hooks['inbox'], array( $inbox, 'load' ) );

		/**
		 * Fires after the Acme Forms menu was registered (add-ons add their screens here).
		 *
		 * @param string $parent_slug Menu slug.
		 */
		do_action( 'acme_forms_admin_menu', self::MENU_SLUG );
	}

	/**
	 * Admin CSS on our screens.
	 *
	 * @param string $hook_suffix Current screen.
	 */
	public function assets( $hook_suffix ) {
		if ( in_array( $hook_suffix, $this->hooks, true ) ) {
			wp_enqueue_style( 'acme-forms-admin', ACME_FORMS_URL . 'assets/admin.css', array(), ACME_FORMS_VERSION );
		}
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . Settings_Page::SLUG ) ) . '">' . esc_html__( 'Settings', 'acme-forms' ) . '</a>'
		);
		return $links;
	}
}
