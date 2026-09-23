<?php
/**
 * Shortcodes: [acme_directory] and [acme_directory_submit].
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output.
 */
class Shortcodes {

	/**
	 * Register shortcodes and the submission handler.
	 */
	public static function register() {
		add_shortcode( 'acme_directory', array( __CLASS__, 'directory' ) );
		add_shortcode( 'acme_directory_submit', array( __CLASS__, 'submit_form' ) );
		add_action( 'admin_post_acme_directory_submit', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * [acme_directory category="" limit=""]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function directory( $atts ) {
		$atts = shortcode_atts(
			array(
				'category' => '',
				'limit'    => (int) Settings::get( 'per_page' ),
			),
			$atts,
			'acme_directory'
		);

		$rows = Listings::query(
			array(
				'category' => sanitize_title( $atts['category'] ),
				'per_page' => max( 1, (int) $atts['limit'] ),
			)
		);

		if ( ! $rows ) {
			return '<div class="acme-directory acme-directory--empty"><p>' . esc_html__( 'No listings yet.', 'acme-directory' ) . '</p></div>';
		}

		$html = '<div class="acme-directory"><ul class="acme-directory__list">';
		foreach ( $rows as $row ) {
			$classes = 'acme-directory__item' . ( $row->featured ? ' is-featured' : '' );
			$html   .= '<li class="' . esc_attr( $classes ) . '" data-listing-id="' . esc_attr( $row->id ) . '">';
			$html   .= '<h3 class="acme-directory__name">';
			$html   .= $row->url ? '<a href="' . esc_url( $row->url ) . '">' . esc_html( $row->name ) . '</a>' : esc_html( $row->name );
			$html   .= '</h3>';
			if ( $row->category_name ) {
				$html .= '<span class="acme-directory__category">' . esc_html( $row->category_name ) . '</span>';
			}
			if ( $row->description ) {
				$html .= '<p class="acme-directory__description">' . esc_html( $row->description ) . '</p>';
			}
			if ( $row->phone ) {
				$html .= '<span class="acme-directory__phone">' . esc_html( $row->phone ) . '</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ul></div>';

		return $html;
	}

	/**
	 * [acme_directory_submit] — logged-in members can suggest a listing.
	 *
	 * @return string
	 */
	public static function submit_form() {
		if ( ! is_user_logged_in() ) {
			return '<p class="acme-directory-submit__login">' . esc_html__( 'Please log in to suggest a listing.', 'acme-directory' ) . '</p>';
		}

		$notice = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['acme_directory_submitted'] ) ) {
			$notice = '<p class="acme-directory-submit__thanks">' . esc_html__( 'Thanks! Your listing will appear once it has been reviewed.', 'acme-directory' ) . '</p>';
		}

		$options = '';
		foreach ( Categories::all() as $category ) {
			$options .= '<option value="' . esc_attr( $category->slug ) . '">' . esc_html( $category->name ) . '</option>';
		}

		ob_start();
		?>
		<form class="acme-directory-submit" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="action" value="acme_directory_submit" />
			<?php wp_nonce_field( 'acme_directory_submit', '_acme_directory_nonce' ); ?>
			<p><label><?php esc_html_e( 'Business name', 'acme-directory' ); ?> <input type="text" name="name" required /></label></p>
			<p><label><?php esc_html_e( 'Category', 'acme-directory' ); ?> <select name="category"><?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select></label></p>
			<p><label><?php esc_html_e( 'Website', 'acme-directory' ); ?> <input type="url" name="url" /></label></p>
			<p><label><?php esc_html_e( 'Phone', 'acme-directory' ); ?> <input type="text" name="phone" /></label></p>
			<p><label><?php esc_html_e( 'Description', 'acme-directory' ); ?> <textarea name="description"></textarea></label></p>
			<p><button type="submit"><?php esc_html_e( 'Suggest listing', 'acme-directory' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * admin-post handler for the submission form.
	 */
	public static function handle_submit() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in.', 'acme-directory' ), 403 );
		}
		check_admin_referer( 'acme_directory_submit', '_acme_directory_nonce' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Listings::create().
		$category = isset( $_POST['category'] ) ? Categories::get_by_slug( sanitize_title( wp_unslash( $_POST['category'] ) ) ) : null;
		$id       = Listings::create(
			array(
				'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'category_id' => $category ? (int) $category->id : 0,
				'url'         => isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '',
				'phone'       => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'status'      => Settings::get( 'moderation' ) ? 'pending' : 'published',
			)
		);
		// phpcs:enable

		if ( ! is_wp_error( $id ) ) {
			$to = Settings::get( 'notify_email' );
			if ( $to ) {
				/* translators: %s: listing name */
				wp_mail( $to, sprintf( __( 'New directory submission: %s', 'acme-directory' ), Listings::get( $id )->name ), admin_url( 'admin.php?page=acme-directory' ) );
			}
		}

		wp_safe_redirect( add_query_arg( 'acme_directory_submitted', 1, wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}
}
