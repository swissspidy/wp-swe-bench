<?php
/**
 * Tools → Redirects screen.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI: list, add/edit form, delete, CSV export.
 */
class Admin {

	const PAGE = 'acme-redirects';

	/**
	 * Repository.
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules Repository.
	 */
	public function __construct( Rule_Repository $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_acme_redirects_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_acme_redirects_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_acme_redirects_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_acme_redirects_bulk', array( $this, 'handle_bulk' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_management_page(
			__( 'Redirects', 'acme-redirects' ),
			__( 'Redirects', 'acme-redirects' ),
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
		if ( 'tools_page_' . self::PAGE === $hook ) {
			wp_enqueue_style( 'acme-redirects-admin', ACME_REDIRECTS_URL . 'assets/admin.css', array(), ACME_REDIRECTS_VERSION );
		}
	}

	/**
	 * Screen URL.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * Renders the screen.
	 */
	public function render() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage redirects.', 'acme-redirects' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_form();
			return;
		}

		$table = new List_Table( $this->rules );
		$table->prepare_items();
		?>
		<div class="wrap acme-redirects">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Redirects', 'acme-redirects' ); ?></h1>
			<a href="<?php echo esc_url( self::url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Redirect', 'acme-redirects' ); ?></a>
			<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acme_redirects_export' ), 'acme_redirects_export' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'acme-redirects' ); ?></a>
			<hr class="wp-header-end">
			<?php $this->notices(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<?php $table->search_box( __( 'Search redirects', 'acme-redirects' ), 'acme-redirects' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acme_redirects_bulk">
				<?php wp_nonce_field( 'acme_redirects_bulk' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Add/edit form.
	 */
	private function render_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$rule = $id ? $this->rules->find( $id ) : new Rule();
		if ( ! $rule ) {
			wp_die( esc_html__( 'Redirect not found.', 'acme-redirects' ) );
		}
		$old = get_transient( 'acme_redirects_form_' . get_current_user_id() );
		if ( is_array( $old ) ) {
			delete_transient( 'acme_redirects_form_' . get_current_user_id() );
			$rule = Rule::from_array( array_merge( $rule->to_array(), $old ) );
		}
		?>
		<div class="wrap acme-redirects">
			<h1><?php echo $id ? esc_html__( 'Edit Redirect', 'acme-redirects' ) : esc_html__( 'Add New Redirect', 'acme-redirects' ); ?></h1>
			<?php $this->notices(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acme-redirects-form">
				<input type="hidden" name="action" value="acme_redirects_save">
				<input type="hidden" name="id" value="<?php echo esc_attr( $rule->id ); ?>">
				<?php wp_nonce_field( 'acme_redirects_save' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acme-source"><?php esc_html_e( 'Source', 'acme-redirects' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="acme-source" name="source" value="<?php echo esc_attr( $rule->source ); ?>" required>
						<p class="description"><?php esc_html_e( 'A path like /old-page/, or a regular expression like ^/blog/(\d+)/?$', 'acme-redirects' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-match-type"><?php esc_html_e( 'Match', 'acme-redirects' ); ?></label></th>
						<td><select id="acme-match-type" name="match_type">
							<?php foreach ( match_types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rule->match_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-target"><?php esc_html_e( 'Target', 'acme-redirects' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="acme-target" name="target" value="<?php echo esc_attr( $rule->target ); ?>">
						<p class="description"><?php esc_html_e( 'A path on this site or a full URL. Leave empty for 410 Gone.', 'acme-redirects' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-status"><?php esc_html_e( 'Status', 'acme-redirects' ); ?></label></th>
						<td><select id="acme-status" name="status">
							<?php foreach ( status_codes() as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $rule->status, $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-priority"><?php esc_html_e( 'Priority', 'acme-redirects' ); ?></label></th>
						<td><input type="number" min="0" max="100" id="acme-priority" name="priority" value="<?php echo esc_attr( $rule->priority ); ?>">
						<p class="description"><?php esc_html_e( 'Rules with a lower number are checked first.', 'acme-redirects' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled', 'acme-redirects' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $rule->enabled ); ?>> <?php esc_html_e( 'Rule is active', 'acme-redirects' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-note"><?php esc_html_e( 'Note', 'acme-redirects' ); ?></label></th>
						<td><input type="text" class="regular-text" id="acme-note" name="note" value="<?php echo esc_attr( $rule->note ); ?>"></td>
					</tr>
				</table>
				<?php submit_button( $rule->id ? __( 'Update Redirect', 'acme-redirects' ) : __( 'Add Redirect', 'acme-redirects' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Admin notices from the redirect query args.
	 */
	private function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! empty( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Redirect saved.', 'acme-redirects' ) . '</p></div>';
		}
		if ( ! empty( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Redirect deleted.', 'acme-redirects' ) . '</p></div>';
		}
		if ( ! empty( $_GET['bulk'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Redirects updated.', 'acme-redirects' ) . '</p></div>';
		}
		// phpcs:enable
		$errors = get_transient( 'acme_redirects_errors_' . get_current_user_id() );
		if ( is_array( $errors ) && $errors ) {
			delete_transient( 'acme_redirects_errors_' . get_current_user_id() );
			echo '<div class="notice notice-error"><ul>';
			foreach ( $errors as $message ) {
				echo '<li>' . esc_html( $message ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	/**
	 * Saves the add/edit form.
	 */
	public function handle_save() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage redirects.', 'acme-redirects' ), 403 );
		}
		check_admin_referer( 'acme_redirects_save' );

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$input = array(
			'source'     => isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '',
			'target'     => isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '',
			'match_type' => isset( $_POST['match_type'] ) ? sanitize_key( $_POST['match_type'] ) : 'exact',
			'status'     => isset( $_POST['status'] ) ? absint( $_POST['status'] ) : 301,
			'priority'   => isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '',
			'enabled'    => ! empty( $_POST['enabled'] ),
			'note'       => isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '',
		);

		if ( $id && ! $this->rules->find( $id ) ) {
			wp_die( esc_html__( 'Redirect not found.', 'acme-redirects' ), 404 );
		}

		$validator = new Rule_Validator( $this->rules );
		$data      = $validator->validate( $input, array( 'id' => $id ) );
		if ( is_wp_error( $data ) ) {
			set_transient( 'acme_redirects_errors_' . get_current_user_id(), $data->get_error_messages(), MINUTE_IN_SECONDS );
			set_transient( 'acme_redirects_form_' . get_current_user_id(), $input, MINUTE_IN_SECONDS );
			wp_safe_redirect( self::url( $id ? array( 'action' => 'edit', 'id' => $id ) : array( 'action' => 'new' ) ) );
			exit;
		}

		$rule   = Rule::from_array( $data );
		$result = $id ? $this->rules->update( $rule ) : $this->rules->insert( $rule );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}

	/**
	 * Deletes a rule (row action).
	 */
	public function handle_delete() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage redirects.', 'acme-redirects' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'acme_redirects_delete_' . $id );
		$this->rules->delete( $id );
		wp_safe_redirect( self::url( array( 'deleted' => 1 ) ) );
		exit;
	}

	/**
	 * Bulk actions: enable, disable, reset hits, delete.
	 */
	public function handle_bulk() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage redirects.', 'acme-redirects' ), 403 );
		}
		check_admin_referer( 'acme_redirects_bulk' );

		$action = '';
		foreach ( array( 'action2', 'bulk_action' ) as $key ) {
			if ( ! empty( $_POST[ $key ] ) && '-1' !== $_POST[ $key ] ) {
				$action = sanitize_key( $_POST[ $key ] );
			}
		}
		$ids = isset( $_POST['rule'] ) ? array_map( 'absint', (array) $_POST['rule'] ) : array();

		foreach ( $ids as $id ) {
			$rule = $this->rules->find( $id );
			if ( ! $rule ) {
				continue;
			}
			switch ( $action ) {
				case 'enable':
				case 'disable':
					$rule->enabled = ( 'enable' === $action );
					$this->rules->update( $rule );
					break;
				case 'reset_hits':
					$this->rules->reset_hits( $id );
					break;
				case 'delete':
					$this->rules->delete( $id );
					break;
			}
		}

		wp_safe_redirect( self::url( array( 'bulk' => 1 ) ) );
		exit;
	}

	/**
	 * CSV export of all rules (same format the 1.x importer understood).
	 */
	public function handle_export() {
		if ( ! current_user_can( Installer::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage redirects.', 'acme-redirects' ), 403 );
		}
		check_admin_referer( 'acme_redirects_export' );

		// @todo This loads everything into memory; fine for a few thousand rules.
		$result = $this->rules->query( array( 'orderby' => 'priority' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirects-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, Rule::csv_columns(), ',', '"', '' );
		foreach ( $result['items'] as $rule ) {
			fputcsv( $out, array_values( $rule->to_csv_row() ), ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
