<?php
/**
 * Admin screens.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * CRM → Contacts and CRM → Add contact.
 */
class Admin {

	/**
	 * Hook up the admin.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_acme_crm_add_contact', array( __CLASS__, 'handle_add' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu() {
		add_menu_page( __( 'CRM', 'acme-crm' ), __( 'CRM', 'acme-crm' ), 'edit_others_posts', 'acme-crm', array( __CLASS__, 'render_contacts' ), 'dashicons-id-alt', 27 );
		add_submenu_page( 'acme-crm', __( 'Contacts', 'acme-crm' ), __( 'Contacts', 'acme-crm' ), 'edit_others_posts', 'acme-crm', array( __CLASS__, 'render_contacts' ) );
		add_submenu_page( 'acme-crm', __( 'Add contact', 'acme-crm' ), __( 'Add contact', 'acme-crm' ), 'edit_others_posts', 'acme-crm-add', array( __CLASS__, 'render_add' ) );
	}

	/**
	 * Contacts list.
	 */
	public static function render_contacts() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$stage  = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( $_GET['stage'] ) ) : '';
		$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable
		$result = Contacts::query(
			array(
				'search' => $search,
				'stage'  => $stage,
				'page'   => $page,
			)
		);
		$stages = Stages::all();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Contacts', 'acme-crm' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=acme-crm-add' ) ); ?>"><?php esc_html_e( 'Add contact', 'acme-crm' ); ?></a>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( Export::url() ); ?>"><?php esc_html_e( 'Export CSV', 'acme-crm' ); ?></a>
			<?php endif; ?>
			<form method="get">
				<input type="hidden" name="page" value="acme-crm" />
				<p class="search-box">
					<select name="stage">
						<option value=""><?php esc_html_e( 'All stages', 'acme-crm' ); ?></option>
						<?php foreach ( $stages as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $stage, $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" />
					<?php submit_button( __( 'Filter', 'acme-crm' ), '', '', false ); ?>
				</p>
			</form>
			<p><?php /* translators: %d: number of contacts */ echo esc_html( sprintf( _n( '%d contact', '%d contacts', $result['total'], 'acme-crm' ), $result['total'] ) ); ?></p>
			<table class="widefat striped acme-crm-contacts">
				<thead><tr><th><?php esc_html_e( 'Last name', 'acme-crm' ); ?></th><th><?php esc_html_e( 'First name', 'acme-crm' ); ?></th><th><?php esc_html_e( 'Email', 'acme-crm' ); ?></th><th><?php esc_html_e( 'Company', 'acme-crm' ); ?></th><th><?php esc_html_e( 'Stage', 'acme-crm' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $result['items'] as $row ) : ?>
					<?php $c = Contacts::to_array( $row ); ?>
					<tr>
						<td><?php echo esc_html( $c['last_name'] ); ?></td>
						<td><?php echo esc_html( $c['first_name'] ); ?></td>
						<td><?php echo esc_html( $c['email'] ); ?></td>
						<td><?php echo esc_html( $c['company'] ); ?></td>
						<td><?php echo esc_html( isset( $stages[ $c['stage'] ] ) ? $stages[ $c['stage'] ] : $c['stage'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Add-contact screen.
	 */
	public static function render_add() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Add contact', 'acme-crm' ); ?></h1>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The contact could not be saved.', 'acme-crm' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acme_crm_add_contact" />
				<?php wp_nonce_field( 'acme_crm_add_contact' ); ?>
				<table class="form-table">
					<tr><th><label for="acme-crm-first-name"><?php esc_html_e( 'First name', 'acme-crm' ); ?></label></th><td><input id="acme-crm-first-name" type="text" name="first_name" class="regular-text" /></td></tr>
					<tr><th><label for="acme-crm-last-name"><?php esc_html_e( 'Last name', 'acme-crm' ); ?></label></th><td><input id="acme-crm-last-name" type="text" name="last_name" class="regular-text" /></td></tr>
					<tr><th><label for="acme-crm-email"><?php esc_html_e( 'Email', 'acme-crm' ); ?></label></th><td><input id="acme-crm-email" type="email" name="email" class="regular-text" /></td></tr>
					<tr><th><label for="acme-crm-phone"><?php esc_html_e( 'Phone', 'acme-crm' ); ?></label></th><td><input id="acme-crm-phone" type="text" name="phone" /></td></tr>
					<tr><th><label for="acme-crm-company"><?php esc_html_e( 'Company', 'acme-crm' ); ?></label></th><td><input id="acme-crm-company" type="text" name="company" class="regular-text" /></td></tr>
					<tr><th><label for="acme-crm-stage"><?php esc_html_e( 'Stage', 'acme-crm' ); ?></label></th><td><select id="acme-crm-stage" name="stage">
						<?php foreach ( Stages::all() as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				</table>
				<?php submit_button( __( 'Add contact', 'acme-crm' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save a contact from the add screen.
	 */
	public static function handle_add() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to add contacts.', 'acme-crm' ), 403 );
		}
		check_admin_referer( 'acme_crm_add_contact' );
		$fields = array();
		foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'company', 'stage' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$fields[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		$id = Contacts::create( $fields );
		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=acme-crm-add&error=1' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=acme-crm&added=' . $id ) );
		exit;
	}
}
