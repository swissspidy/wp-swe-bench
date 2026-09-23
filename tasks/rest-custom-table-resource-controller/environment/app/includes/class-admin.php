<?php
/**
 * wp-admin: Leads screen, status changes, dashboard widget.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class Admin {

	const PAGE = 'acme-leads';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_acme_leads_status', array( $this, 'handle_status_change' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_menu_page(
			__( 'Leads', 'acme-leads' ),
			__( 'Leads', 'acme-leads' ),
			Installer::CAP_VIEW,
			self::PAGE,
			array( $this, 'render_page' ),
			'dashicons-groups',
			26
		);
	}

	/**
	 * Styles.
	 *
	 * @param string $hook_suffix Page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'toplevel_page_' . self::PAGE === $hook_suffix ) {
			wp_enqueue_style( 'acme-leads-admin', ACME_LEADS_URL . 'assets/admin.css', array(), ACME_LEADS_VERSION );
		}
	}

	/**
	 * The Leads screen. Reps only see their own leads.
	 */
	public function render_page() {
		if ( ! current_user_can( Installer::CAP_VIEW ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view leads.', 'acme-leads' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen.
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'created_at', 'name', 'score' ), true ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = isset( $_GET['order'] ) && 'asc' === $_GET['order'] ? 'ASC' : 'DESC';
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$result = Repository::query(
			array(
				'status'   => in_array( $status, Repository::STATUSES, true ) ? $status : '',
				'owner'    => current_user_can( Installer::CAP_MANAGE ) ? 0 : get_current_user_id(),
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => 50,
				'page'     => $page,
			)
		);
		$labels = acme_leads_status_labels();
		$pages  = (int) ceil( $result['total'] / 50 );
		?>
		<div class="wrap acme-leads">
			<h1><?php esc_html_e( 'Leads', 'acme-leads' ); ?></h1>
			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>"><?php esc_html_e( 'All', 'acme-leads' ); ?></a> |</li>
				<?php foreach ( $labels as $key => $label ) : ?>
					<li><a href="<?php echo esc_url( add_query_arg( 'status', $key, admin_url( 'admin.php?page=' . self::PAGE ) ) ); ?>"><?php echo esc_html( $label ); ?></a> |</li>
				<?php endforeach; ?>
			</ul>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'acme-leads' ); ?></th>
						<th><?php esc_html_e( 'Email', 'acme-leads' ); ?></th>
						<th><?php esc_html_e( 'Company', 'acme-leads' ); ?></th>
						<th><?php esc_html_e( 'Score', 'acme-leads' ); ?></th>
						<th><?php esc_html_e( 'Status', 'acme-leads' ); ?></th>
						<th><?php esc_html_e( 'Created', 'acme-leads' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $result['items'] as $lead ) : ?>
						<tr>
							<td><?php echo esc_html( $lead['name'] ); ?></td>
							<td><a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a></td>
							<td><?php echo esc_html( $lead['company'] ); ?></td>
							<td><?php echo (int) $lead['score']; ?></td>
							<td>
								<?php if ( current_user_can( Installer::CAP_MANAGE ) ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'acme_leads_status_' . $lead['id'] ); ?>
										<input type="hidden" name="action" value="acme_leads_status" />
										<input type="hidden" name="lead" value="<?php echo (int) $lead['id']; ?>" />
										<select name="status" onchange="this.form.submit()">
											<?php foreach ( $labels as $key => $label ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $lead['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</form>
								<?php else : ?>
									<?php echo esc_html( $labels[ $lead['status'] ] ?? $lead['status'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( get_date_from_gmt( $lead['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $pages > 1 ) : ?>
				<p class="acme-leads__pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $page,
								'total'   => $pages,
							)
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * admin-post handler for the status dropdown.
	 */
	public function handle_status_change() {
		$id = isset( $_POST['lead'] ) ? absint( $_POST['lead'] ) : 0;
		check_admin_referer( 'acme_leads_status_' . $id );
		if ( ! current_user_can( Installer::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit leads.', 'acme-leads' ), 403 );
		}
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		Repository::update_status( $id, $status );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Dashboard widget: leads per status.
	 */
	public function dashboard_widget() {
		if ( ! current_user_can( Installer::CAP_MANAGE ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'acme_leads_pipeline',
			__( 'Sales pipeline', 'acme-leads' ),
			function () {
				$counts = Repository::count_by_status();
				echo '<ul class="acme-leads-pipeline">';
				foreach ( acme_leads_status_labels() as $key => $label ) {
					printf( '<li><strong>%d</strong> %s</li>', (int) $counts[ $key ], esc_html( $label ) );
				}
				echo '</ul>';
			}
		);
	}
}
