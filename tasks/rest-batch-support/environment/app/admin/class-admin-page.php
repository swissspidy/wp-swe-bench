<?php
/**
 * The "Tasks" screen in wp-admin.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen: a small vanilla-JS client of the REST API (assets/admin.js).
 */
class Admin_Page {

	const SLUG = 'acme-tasks';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register the menu page.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'Tasks', 'acme-tasks' ),
			__( 'Tasks', 'acme-tasks' ),
			'edit_posts',
			self::SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-list-view',
			26
		);
	}

	/**
	 * Enqueue the screen's assets.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		$url = plugin_dir_url( PLUGIN_FILE );
		wp_enqueue_style( 'acme-tasks-admin', $url . 'assets/admin.css', array(), VERSION );
		wp_enqueue_script( 'acme-tasks-admin', $url . 'assets/admin.js', array( 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready' ), VERSION, true );
		wp_set_script_translations( 'acme-tasks-admin', 'acme-tasks', dirname( __DIR__ ) . '/languages' );

		$users = array();
		foreach ( get_users( array( 'capability' => 'edit_posts' ) ) as $user ) {
			$users[] = array(
				'id'   => $user->ID,
				'name' => $user->display_name,
			);
		}
		wp_localize_script(
			'acme-tasks-admin',
			'acmeTasks',
			array(
				'namespace' => REST_NAMESPACE,
				'userId'    => get_current_user_id(),
				'users'     => $users,
			)
		);
	}

	/**
	 * Render the page shell; admin.js does the rest.
	 */
	public static function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tasks', 'acme-tasks' ); ?></h1>
			<div id="acme-tasks-app" class="acme-tasks">
				<p class="acme-tasks-loading"><?php esc_html_e( 'Loading…', 'acme-tasks' ); ?></p>
			</div>
		</div>
		<?php
	}
}
