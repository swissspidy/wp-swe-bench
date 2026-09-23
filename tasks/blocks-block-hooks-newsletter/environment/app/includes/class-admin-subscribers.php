<?php
/**
 * Users → Subscribers screen + CSV export.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only list of subscribers.
 */
class Admin_Subscribers {

	const PAGE     = 'acme-newsletter-subscribers';
	const PER_PAGE = 50;

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_acme_newsletter_export', array( $this, 'export' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_users_page(
			__( 'Newsletter subscribers', 'acme-newsletter' ),
			__( 'Subscribers', 'acme-newsletter' ),
			'list_users',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Screen.
	 */
	public function render() {
		if ( ! current_user_can( 'list_users' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$rows    = Subscribers::latest( self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE );
		$sources = get_sources();
		$counts  = Subscribers::count_by_source();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Newsletter subscribers', 'acme-newsletter' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acme_newsletter_export' ), 'acme_newsletter_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'acme-newsletter' ); ?></a>
			<ul class="subsubsub">
				<?php foreach ( $counts as $source => $total ) : ?>
					<li><?php echo esc_html( $sources[ $source ] ?? ( '' === $source ? __( 'Unknown', 'acme-newsletter' ) : $source ) ); ?> <span class="count">(<?php echo (int) $total; ?>)</span></li>
				<?php endforeach; ?>
			</ul>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'acme-newsletter' ); ?></th>
						<th><?php esc_html_e( 'Name', 'acme-newsletter' ); ?></th>
						<th><?php esc_html_e( 'Source', 'acme-newsletter' ); ?></th>
						<th><?php esc_html_e( 'Status', 'acme-newsletter' ); ?></th>
						<th><?php esc_html_e( 'Date', 'acme-newsletter' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No subscribers yet.', 'acme-newsletter' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->email ); ?></td>
						<td><?php echo esc_html( $row->name ); ?></td>
						<td><?php echo esc_html( $sources[ $row->source ] ?? $row->source ); ?></td>
						<td><?php echo esc_html( $row->status ); ?></td>
						<td><?php echo esc_html( get_date_from_gmt( $row->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * CSV export.
	 */
	public function export() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export subscribers.', 'acme-newsletter' ), 403 );
		}
		check_admin_referer( 'acme_newsletter_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'email', 'name', 'source', 'status', 'created_at' ) );
		$offset = 0;
		do {
			$rows = Subscribers::latest( 500, $offset );
			foreach ( $rows as $row ) {
				fputcsv( $out, array( $row->email, $row->name, $row->source, $row->status, $row->created_at ) );
			}
			$offset += 500;
		} while ( count( $rows ) === 500 );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
