<?php
/**
 * Settings → Order Sync.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync\Admin;

use Acme\OrdersSync\Settings;
use const Acme\OrdersSync\REST_NAMESPACE;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class Settings_Page {

	const SLUG = 'acme-orders-sync';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu(): void {
		add_options_page(
			__( 'Order Sync', 'acme-orders-sync' ),
			__( 'Order Sync', 'acme-orders-sync' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$all     = $this->settings->all();
		$sources = array_values(
			array_map(
				static fn( $id, $s ) => array_merge( $s, array( 'id' => $id ) ),
				array_keys( (array) $all['sources'] ),
				(array) $all['sources']
			)
		);
		// Three empty rows for new storefronts.
		for ( $i = 0; $i < 3; $i++ ) {
			$sources[] = array(
				'id'     => '',
				'label'  => '',
				'secret' => '',
			);
		}
		$name = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Order Sync', 'acme-orders-sync' ); ?></h1>
			<p>
				<?php esc_html_e( 'Webhook URL to enter in the Acme Shop admin:', 'acme-orders-sync' ); ?>
				<code><?php echo esc_html( rest_url( REST_NAMESPACE . '/webhook' ) ); ?></code>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'acme_orders_sync' ); ?>
				<h2><?php esc_html_e( 'Storefronts', 'acme-orders-sync' ); ?></h2>
				<table class="widefat striped acme-orders-sources">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Source ID', 'acme-orders-sync' ); ?></th>
							<th><?php esc_html_e( 'Label', 'acme-orders-sync' ); ?></th>
							<th><?php esc_html_e( 'Webhook secret', 'acme-orders-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $sources as $i => $source ) : ?>
						<tr>
							<td><input type="text" name="<?php echo esc_attr( "{$name}[sources][$i][id]" ); ?>" value="<?php echo esc_attr( $source['id'] ); ?>" /></td>
							<td><input type="text" name="<?php echo esc_attr( "{$name}[sources][$i][label]" ); ?>" value="<?php echo esc_attr( $source['label'] ); ?>" /></td>
							<td><input type="password" autocomplete="off" name="<?php echo esc_attr( "{$name}[sources][$i][secret]" ); ?>" value="<?php echo esc_attr( $source['secret'] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( get_option( Settings::LEGACY_SECRET ) && ! isset( $all['sources']['default'] ) ) : ?>
					<p class="description"><?php esc_html_e( 'The "default" storefront uses the webhook secret configured in version 1.0.', 'acme-orders-sync' ); ?></p>
				<?php endif; ?>
				<h2><?php esc_html_e( 'Logging', 'acme-orders-sync' ); ?></h2>
				<select name="<?php echo esc_attr( $name ); ?>[log_level]">
					<?php foreach ( Settings::LOG_LEVELS as $level ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $all['log_level'], $level ); ?>><?php echo esc_html( $level ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
