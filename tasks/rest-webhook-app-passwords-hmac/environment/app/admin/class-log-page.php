<?php
/**
 * Tools → Order Sync Log.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync\Admin;

use Acme\OrdersSync\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Log viewer.
 */
class Log_Page {

	const SLUG = 'acme-orders-sync-log';

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_acme_orders_clear_log', array( $this, 'clear' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu(): void {
		add_management_page(
			__( 'Order Sync Log', 'acme-orders-sync' ),
			__( 'Order Sync Log', 'acme-orders-sync' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Clears the log.
	 */
	public function clear(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'acme-orders-sync' ), 403 );
		}
		check_admin_referer( 'acme_orders_clear_log' );
		$this->logger->clear();
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::SLUG . '&cleared=1' ) );
		exit;
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$entries = $this->logger->tail( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Order Sync Log', 'acme-orders-sync' ); ?></h1>
			<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Log cleared.', 'acme-orders-sync' ); ?></p></div>
			<?php endif; ?>
			<table class="widefat striped acme-orders-log">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'acme-orders-sync' ); ?></th>
						<th><?php esc_html_e( 'Level', 'acme-orders-sync' ); ?></th>
						<th><?php esc_html_e( 'Message', 'acme-orders-sync' ); ?></th>
						<th><?php esc_html_e( 'Details', 'acme-orders-sync' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $entries ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'The log is empty.', 'acme-orders-sync' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $entries as $entry ) : ?>
					<tr class="level-<?php echo esc_attr( (string) ( $entry['level'] ?? '' ) ); ?>">
						<td><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $entry['level'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
						<td><pre><?php echo esc_html( (string) wp_json_encode( $entry['context'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acme_orders_clear_log" />
				<?php wp_nonce_field( 'acme_orders_clear_log' ); ?>
				<?php submit_button( __( 'Clear log', 'acme-orders-sync' ), 'delete' ); ?>
			</form>
		</div>
		<?php
	}
}
