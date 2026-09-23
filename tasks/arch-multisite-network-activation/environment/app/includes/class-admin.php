<?php
/**
 * Admin screens.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Directory → Listings (moderation queue) and Directory → Settings.
 */
class Admin {

	/**
	 * Hook up the admin.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_post_acme_directory_moderate', array( __CLASS__, 'handle_moderate' ) );
	}

	/**
	 * Menu entries.
	 */
	public static function menu() {
		$counts = Listings::status_counts();
		$badge  = $counts['pending'] ? ' <span class="awaiting-mod">' . (int) $counts['pending'] . '</span>' : '';

		add_menu_page(
			__( 'Directory', 'acme-directory' ),
			__( 'Directory', 'acme-directory' ) . $badge,
			'manage_options',
			'acme-directory',
			array( __CLASS__, 'render_listings' ),
			'dashicons-store',
			26
		);
		add_submenu_page(
			'acme-directory',
			__( 'Directory settings', 'acme-directory' ),
			__( 'Settings', 'acme-directory' ),
			'manage_options',
			'acme-directory-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Listings screen.
	 */
	public static function render_listings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'pending';
		if ( ! in_array( $status, Listings::STATUSES, true ) ) {
			$status = 'pending';
		}
		$rows   = Listings::query(
			array(
				'status'   => $status,
				'per_page' => 100,
			)
		);
		$counts = Listings::status_counts();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Directory listings', 'acme-directory' ); ?></h1>
			<ul class="subsubsub">
				<?php foreach ( $counts as $key => $count ) : ?>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=acme-directory&status=' . $key ) ); ?>" <?php echo $key === $status ? 'class="current"' : ''; ?>><?php echo esc_html( ucfirst( $key ) ); ?> (<?php echo (int) $count; ?>)</a> </li>
				<?php endforeach; ?>
			</ul>
			<table class="widefat striped acme-directory-listings">
				<thead><tr><th><?php esc_html_e( 'Name', 'acme-directory' ); ?></th><th><?php esc_html_e( 'Category', 'acme-directory' ); ?></th><th><?php esc_html_e( 'Expires', 'acme-directory' ); ?></th><th><?php esc_html_e( 'Actions', 'acme-directory' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Nothing here.', 'acme-directory' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->name ); ?></td>
						<td><?php echo esc_html( $row->category_name ? $row->category_name : '—' ); ?></td>
						<td><?php echo esc_html( $row->expires_at ? $row->expires_at : '—' ); ?></td>
						<td>
							<?php
							foreach ( array( 'published' => __( 'Approve', 'acme-directory' ), 'rejected' => __( 'Reject', 'acme-directory' ) ) as $new => $label ) {
								if ( $new === $row->status ) {
									continue;
								}
								$url = wp_nonce_url( admin_url( 'admin-post.php?action=acme_directory_moderate&id=' . (int) $row->id . '&to=' . $new ), 'acme_directory_moderate_' . (int) $row->id );
								echo '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Settings screen.
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Directory settings', 'acme-directory' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'acme_directory' );
				do_settings_sections( 'acme-directory-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Approve / reject a listing.
	 */
	public static function handle_moderate() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$to = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to moderate listings.', 'acme-directory' ), 403 );
		}
		check_admin_referer( 'acme_directory_moderate_' . $id );
		Listings::set_status( $id, $to );
		wp_safe_redirect( admin_url( 'admin.php?page=acme-directory&status=' . $to ) );
		exit;
	}
}
