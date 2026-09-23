<?php
/**
 * Products → Import screen.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Upload form, settings and the result of the last import.
 */
class Admin {

	const PAGE = 'acme-importer';

	/**
	 * Importer.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param Importer $importer Importer.
	 */
	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_acme_importer_upload', array( $this, 'handle_upload' ) );
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
				'default'           => array( 'default_status' => 'draft' ),
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
			<p><?php esc_html_e( 'CSV with a header row. Columns: sku (required), name, price, stock, status, categories (separated by |), description. Products are matched by SKU: existing products are updated, new ones created.', 'acme-importer' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acme-importer-upload">
				<input type="hidden" name="action" value="acme_importer_upload">
				<?php wp_nonce_field( 'acme_importer_upload' ); ?>
				<input type="file" name="import_file" accept=".csv,text/csv" required>
				<?php submit_button( __( 'Import', 'acme-importer' ), 'primary', 'submit', false ); ?>
			</form>

			<?php $this->render_last_run(); ?>

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
					</table>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Summary of the last import.
	 */
	private function render_last_run() {
		$last = get_option( Importer::LAST_RUN_OPTION );
		if ( ! is_array( $last ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Last import', 'acme-importer' ); ?></h2>
		<table class="widefat striped acme-importer-last-run">
			<tbody>
				<tr><th><?php esc_html_e( 'File', 'acme-importer' ); ?></th><td><?php echo esc_html( $last['file'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Finished', 'acme-importer' ); ?></th><td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['finished'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Created', 'acme-importer' ); ?></th><td><?php echo esc_html( number_format_i18n( $last['created'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Updated', 'acme-importer' ); ?></th><td><?php echo esc_html( number_format_i18n( $last['updated'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Skipped', 'acme-importer' ); ?></th><td><?php echo esc_html( number_format_i18n( $last['skipped'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Failed', 'acme-importer' ); ?></th><td><?php echo esc_html( number_format_i18n( $last['failed'] ) ); ?></td></tr>
			</tbody>
		</table>
		<?php if ( ! empty( $last['errors'] ) ) : ?>
			<h3><?php esc_html_e( 'Errors', 'acme-importer' ); ?></h3>
			<ul class="acme-importer-errors">
				<?php foreach ( $last['errors'] as $error ) : ?>
					<li>
						<?php
						/* translators: 1: row number, 2: SKU, 3: error message */
						echo esc_html( sprintf( __( 'Row %1$d (%2$s): %3$s', 'acme-importer' ), $error['row'], $error['sku'], $error['error'] ) );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Notices after an upload.
	 */
	private function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['imported'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Import finished.', 'acme-importer' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * Handles the upload: imports the file right away.
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

		$result = $this->importer->import_file( $file['tmp_name'], sanitize_file_name( $file['name'] ) );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( self::url( array( 'error' => rawurlencode( $result->get_error_message() ) ) ) );
			exit;
		}

		wp_safe_redirect( self::url( array( 'imported' => 1 ) ) );
		exit;
	}
}
