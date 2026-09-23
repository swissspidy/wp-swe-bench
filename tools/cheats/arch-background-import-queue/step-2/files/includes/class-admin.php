<?php
/**
 * Products → Import screen.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Upload form, settings and the list of imports.
 */
class Admin {

	const PAGE = 'acme-importer';

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	protected $queue;

	/**
	 * Constructor.
	 *
	 * @param Queue $queue Queue.
	 */
	public function __construct( Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_acme_importer_upload', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_acme_importer_cancel', array( $this, 'handle_cancel' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Screen URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => Product_Type::POST_TYPE,
					'page'      => self::PAGE,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Product_Type::POST_TYPE,
			__( 'Import products', 'acme-importer' ),
			__( 'Import', 'acme-importer' ),
			Installer::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Styles.
	 *
	 * @param string $hook Screen hook.
	 */
	public function assets( $hook ) {
		if ( false !== strpos( $hook, self::PAGE ) ) {
			wp_enqueue_style( 'acme-importer-admin', ACME_IMPORTER_URL . 'assets/admin.css', array(), ACME_IMPORTER_VERSION );
		}
	}

	/**
	 * Settings.
	 */
	public function register_settings() {
		register_setting(
			'acme_importer',
			'acme_importer_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'default_status' => 'draft',
					'batch_size'     => 100,
				),
			)
		);
	}

	/**
	 * Sanitizes the settings.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = settings();
		if ( isset( $input['default_status'] ) && array_key_exists( $input['default_status'], product_statuses() ) ) {
			$output['default_status'] = $input['default_status'];
		}
		if ( isset( $input['batch_size'] ) ) {
			$output['batch_size'] = min( 1000, max( 10, absint( $input['batch_size'] ) ) );
		}
		return $output;
	}

	/**
	 * Renders the screen.
	 */
	public function render() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import products.', 'acme-importer' ) );
		}
		$settings = settings();
		?>
		<div class="wrap acme-importer">
			<h1><?php esc_html_e( 'Import products', 'acme-importer' ); ?></h1>
			<?php $this->notices(); ?>

			<h2><?php esc_html_e( 'Upload a price list', 'acme-importer' ); ?></h2>
			<p><?php esc_html_e( 'CSV with a header row. Columns: sku (required), name, price, stock, status, categories (separated by |), description. Products are matched by SKU: existing products are updated, new ones created. The import runs in the background; you can leave this page.', 'acme-importer' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acme-importer-upload">
				<input type="hidden" name="action" value="acme_importer_upload">
				<?php wp_nonce_field( 'acme_importer_upload' ); ?>
				<input type="file" name="import_file" accept=".csv,text/csv" required>
				<?php submit_button( __( 'Queue import', 'acme-importer' ), 'primary', 'submit', false ); ?>
			</form>

			<?php $this->render_imports(); ?>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<h2><?php esc_html_e( 'Settings', 'acme-importer' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php settings_fields( 'acme_importer' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="acme-default-status"><?php esc_html_e( 'Status of new products', 'acme-importer' ); ?></label></th>
							<td><select id="acme-default-status" name="acme_importer_settings[default_status]">
								<?php foreach ( product_statuses() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['default_status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></td>
						</tr>
						<tr>
							<th scope="row"><label for="acme-batch-size"><?php esc_html_e( 'Rows per batch', 'acme-importer' ); ?></label></th>
							<td><input type="number" min="10" max="1000" id="acme-batch-size" name="acme_importer_settings[batch_size]" value="<?php echo esc_attr( batch_size() ); ?>">
							<p class="description"><?php esc_html_e( 'How many rows one background run imports. Lower it if imports time out.', 'acme-importer' ); ?></p></td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Recent imports with their progress.
	 */
	private function render_imports() {
		$jobs = $this->queue->jobs()->recent( 20 );
		if ( ! $jobs ) {
			return;
		}
		$labels = Job_Store::labels();
		?>
		<h2><?php esc_html_e( 'Imports', 'acme-importer' ); ?></h2>
		<table class="widefat striped acme-importer-imports">
			<thead>
				<tr>
					<th><?php esc_html_e( 'File', 'acme-importer' ); ?></th>
					<th><?php esc_html_e( 'Status', 'acme-importer' ); ?></th>
					<th><?php esc_html_e( 'Progress', 'acme-importer' ); ?></th>
					<th><?php esc_html_e( 'Created / updated / skipped / failed / retrying', 'acme-importer' ); ?></th>
					<th><?php esc_html_e( 'Queued', 'acme-importer' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<?php $data = $this->queue->to_array( $job ); ?>
					<tr id="acme-import-<?php echo esc_attr( $data['id'] ); ?>">
						<td><?php echo esc_html( $data['file_name'] ); ?></td>
						<td class="column-status"><?php echo esc_html( $labels[ $data['status'] ] ?? $data['status'] ); ?></td>
						<td class="column-progress">
							<progress value="<?php echo esc_attr( $data['processed'] ); ?>" max="<?php echo esc_attr( $data['total'] ); ?>"></progress>
							<?php
							/* translators: 1: processed rows, 2: total rows */
							echo esc_html( sprintf( __( '%1$s of %2$s rows', 'acme-importer' ), number_format_i18n( $data['processed'] ), number_format_i18n( $data['total'] ) ) );
							?>
						</td>
						<td><?php echo esc_html( implode( ' / ', array( $data['created'], $data['updated'], $data['skipped'], $data['failed'], $data['retrying'] ) ) ); ?></td>
						<td><?php echo esc_html( $data['created_at'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $data['created_at'] ) ) : '' ); ?></td>
						<td><?php $this->render_actions( $data ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Row actions of an import.
	 *
	 * @param array $data Import.
	 */
	protected function render_actions( array $data ) {
		if ( $data['failed'] > 0 ) {
			printf(
				'<a class="acme-import-error-report" href="%s">%s</a> ',
				esc_url( add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), rest_url( Rest_Controller::NAMESPACE . '/imports/' . $data['id'] . '/errors' ) ) ),
				esc_html__( 'Download error report', 'acme-importer' )
			);
		}
		if ( in_array( $data['status'], array( Job_Store::QUEUED, Job_Store::RUNNING ), true ) ) :
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acme_importer_cancel">
				<input type="hidden" name="import" value="<?php echo esc_attr( $data['id'] ); ?>">
				<?php wp_nonce_field( 'acme_importer_cancel_' . $data['id'] ); ?>
				<?php submit_button( __( 'Cancel', 'acme-importer' ), 'secondary small', 'cancel', false ); ?>
			</form>
			<?php
		endif;
	}

	/**
	 * Notices after an upload.
	 */
	private function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['queued'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The import was queued and runs in the background.', 'acme-importer' ) . '</p></div>';
		}
		if ( isset( $_GET['cancelled'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The import was cancelled.', 'acme-importer' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * Handles the upload: queues the file.
	 */
	public function handle_upload() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import products.', 'acme-importer' ), 403 );
		}
		check_admin_referer( 'acme_importer_upload' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		$file = isset( $_FILES['import_file'] ) ? $_FILES['import_file'] : null;
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_safe_redirect( self::url( array( 'error' => rawurlencode( __( 'Please choose a CSV file.', 'acme-importer' ) ) ) ) );
			exit;
		}

		$job = $this->queue->enqueue( $file['tmp_name'], (string) $file['name'], get_current_user_id() );
		if ( is_wp_error( $job ) ) {
			wp_safe_redirect( self::url( array( 'error' => rawurlencode( $job->get_error_message() ) ) ) );
			exit;
		}

		wp_safe_redirect( self::url( array( 'queued' => $job->id ) ) );
		exit;
	}

	/**
	 * Cancels an import.
	 */
	public function handle_cancel() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import products.', 'acme-importer' ), 403 );
		}
		$id = isset( $_POST['import'] ) ? absint( $_POST['import'] ) : 0;
		check_admin_referer( 'acme_importer_cancel_' . $id );

		$result = $this->queue->cancel( $id );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::url( array( 'error' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'cancelled' => $id ) ) );
		exit;
	}
}
