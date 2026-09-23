<?php
/**
 * wp-admin "Contact entries" screen and CSV export.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Entries screen.
 */
class Entries_Admin {

	const PAGE     = 'acme-contact-entries';
	const CAP      = 'edit_others_posts'; // Administrators and Editors.
	const PER_PAGE = 20;

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_acme_contact_export', array( $this, 'export' ) );
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_menu_page( __( 'Contact entries', 'acme-contact' ), __( 'Contact entries', 'acme-contact' ), self::CAP, self::PAGE, array( $this, 'render' ), 'dashicons-email-alt', 26 );
	}

	/**
	 * Current search term.
	 *
	 * @return string
	 */
	private static function search_term() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		return isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view contact entries.', 'acme-contact' ), '', array( 'response' => 403 ) );
		}
		require_once ACME_CONTACT_DIR . 'includes/class-entries-list-table.php';
		$table = new Entries_List_Table();
		$table->prepare_items();

		$export = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'acme_contact_export',
					's'      => self::search_term(),
				),
				admin_url( 'admin-post.php' )
			),
			'acme_contact_export'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Contact entries', 'acme-contact' ); ?></h1>
			<a class="page-title-action acme-contact-export" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'acme-contact' ); ?></a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search entries', 'acme-contact' ), 'acme-contact-entries' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Neutralize values spreadsheets would evaluate as formulas.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return $value;
	}

	/**
	 * CSV download of all entries matching the current search.
	 */
	public function export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export contact entries.', 'acme-contact' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'acme_contact_export' );

		$entries = Entries::query( self::search_term(), 0 );
		$names   = array();
		foreach ( $entries as $entry ) {
			foreach ( array_keys( $entry['fields'] ) as $name ) {
				if ( ! in_array( (string) $name, $names, true ) ) {
					$names[] = (string) $name;
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="contact-entries-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array_merge( array( 'id', 'created_at', 'post_id', 'form_id', 'email' ), $names ), ',', '"', '' );
		foreach ( $entries as $entry ) {
			$row = array( $entry['id'], $entry['created_at'], $entry['post_id'], self::csv_safe( $entry['form_id'] ), self::csv_safe( $entry['email'] ) );
			foreach ( $names as $name ) {
				$row[] = self::csv_safe( isset( $entry['fields'][ $name ] ) && is_scalar( $entry['fields'][ $name ] ) ? (string) $entry['fields'][ $name ] : '' );
			}
			fputcsv( $out, $row, ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
