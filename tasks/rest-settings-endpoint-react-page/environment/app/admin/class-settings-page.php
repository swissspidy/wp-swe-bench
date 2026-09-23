<?php
/**
 * Settings → SEO screen.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * The settings screen (a classic form posting to admin-post.php).
 */
class Settings_Page {

	const SLUG   = 'acme-seo';
	const ACTION = 'acme_seo_save_settings';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability needed to manage the settings. Editors manage SEO on most of our sites.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability required for the SEO settings screen.
		 *
		 * @since 1.1.0
		 *
		 * @param string $capability Capability.
		 */
		return apply_filters( 'acme_seo_settings_capability', 'edit_others_posts' );
	}

	/**
	 * Register Settings → SEO.
	 */
	public static function add_page() {
		add_options_page(
			__( 'SEO settings', 'acme-seo' ),
			__( 'SEO', 'acme-seo' ),
			self::capability(),
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'acme-seo' ) ) );
		return $links;
	}

	/**
	 * Render the form.
	 */
	public static function render() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$o = Options::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$updated = ! empty( $_GET['updated'] );
		?>
		<div class="wrap acme-seo-settings">
			<h1><?php esc_html_e( 'SEO settings', 'acme-seo' ); ?></h1>
			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'acme-seo' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION, '_acme_seo_nonce' ); ?>

				<h2><?php esc_html_e( 'Titles', 'acme-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acme-seo-separator"><?php esc_html_e( 'Title separator', 'acme-seo' ); ?></label></th>
						<td>
							<select id="acme-seo-separator" name="title_separator">
								<?php foreach ( Options::SEPARATORS as $sep ) : ?>
									<option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $o['title_separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-home-title"><?php esc_html_e( 'Home page title', 'acme-seo' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="acme-seo-home-title" name="home_title" value="<?php echo esc_attr( $o['home_title'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Placeholders: %%sitename%%, %%tagline%%, %%sep%%', 'acme-seo' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-home-description"><?php esc_html_e( 'Home page meta description', 'acme-seo' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="acme-seo-home-description" name="home_description"><?php echo esc_textarea( $o['home_description'] ); ?></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Search engines', 'acme-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Hide from search engines', 'acme-seo' ); ?></th>
						<td>
							<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) : ?>
								<label><input type="checkbox" name="noindex_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $o['noindex_post_types'], true ) ); ?> /> <?php echo esc_html( $type->labels->name ); ?></label><br />
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Archives', 'acme-seo' ); ?></th>
						<td>
							<label><input type="checkbox" name="noindex_archives[author]" value="1" <?php checked( $o['noindex_archives']['author'] ); ?> /> <?php esc_html_e( 'Noindex author archives', 'acme-seo' ); ?></label><br />
							<label><input type="checkbox" name="noindex_archives[date]" value="1" <?php checked( $o['noindex_archives']['date'] ); ?> /> <?php esc_html_e( 'Noindex date archives', 'acme-seo' ); ?></label><br />
							<label><input type="checkbox" name="noindex_archives[tag]" value="1" <?php checked( $o['noindex_archives']['tag'] ); ?> /> <?php esc_html_e( 'Noindex tag archives', 'acme-seo' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'XML sitemap', 'acme-seo' ); ?></th>
						<td>
							<label><input type="checkbox" name="sitemap_enabled" value="1" <?php checked( $o['sitemap_enabled'] ); ?> /> <?php esc_html_e( 'Enable XML sitemap', 'acme-seo' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-sitemap-exclude"><?php esc_html_e( 'Exclude from sitemap (post IDs)', 'acme-seo' ); ?></label></th>
						<td><input type="text" class="regular-text" id="acme-seo-sitemap-exclude" name="sitemap_exclude" value="<?php echo esc_attr( implode( ', ', $o['sitemap_exclude'] ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-google"><?php esc_html_e( 'Google verification code', 'acme-seo' ); ?></label></th>
						<td><input type="text" class="regular-text" id="acme-seo-google" name="verification[google]" value="<?php echo esc_attr( $o['verification']['google'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-bing"><?php esc_html_e( 'Bing verification code', 'acme-seo' ); ?></label></th>
						<td><input type="text" class="regular-text" id="acme-seo-bing" name="verification[bing]" value="<?php echo esc_attr( $o['verification']['bing'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Social', 'acme-seo' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Open Graph', 'acme-seo' ); ?></th>
						<td><label><input type="checkbox" name="og_enabled" value="yes" <?php checked( $o['og_enabled'] ); ?> /> <?php esc_html_e( 'Enable Open Graph tags', 'acme-seo' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-og-image"><?php esc_html_e( 'Default share image (attachment ID)', 'acme-seo' ); ?></label></th>
						<td><input type="number" min="0" id="acme-seo-og-image" name="og_default_image" value="<?php echo esc_attr( $o['og_default_image'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-seo-twitter"><?php esc_html_e( 'Twitter/X username', 'acme-seo' ); ?></label></th>
						<td><input type="text" id="acme-seo-twitter" name="twitter_handle" value="<?php echo esc_attr( $o['twitter_handle'] ); ?>" /></td>
					</tr>
					<?php
					$labels = array(
						'facebook'  => __( 'Facebook page URL', 'acme-seo' ),
						'instagram' => __( 'Instagram URL', 'acme-seo' ),
						'linkedin'  => __( 'LinkedIn URL', 'acme-seo' ),
						'youtube'   => __( 'YouTube channel URL', 'acme-seo' ),
					);
					foreach ( $labels as $network => $label ) :
						?>
						<tr>
							<th scope="row"><label for="acme-seo-<?php echo esc_attr( $network ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="url" class="regular-text" id="acme-seo-<?php echo esc_attr( $network ); ?>" name="social_profiles[<?php echo esc_attr( $network ); ?>]" value="<?php echo esc_attr( $o['social_profiles'][ $network ] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button( __( 'Save changes', 'acme-seo' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save the form (admin-post.php?action=acme_seo_save_settings).
	 */
	public static function save() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'acme-seo' ), 403 );
		}
		check_admin_referer( self::ACTION, '_acme_seo_nonce' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- see below, TODO: sanitize everything consistently.
		$post = wp_unslash( $_POST );

		update_option( 'acme_seo_title_separator', sanitize_text_field( $post['title_separator'] ?? '-' ) );
		update_option( 'acme_seo_home_title', $post['home_title'] ?? '' );
		update_option( 'acme_seo_home_description', $post['home_description'] ?? '' );
		update_option( 'acme_seo_noindex_post_types', isset( $post['noindex_post_types'] ) ? (array) $post['noindex_post_types'] : array() );
		$archives = isset( $post['noindex_archives'] ) ? (array) $post['noindex_archives'] : array();
		update_option(
			'acme_seo_noindex_archives',
			array(
				'author' => empty( $archives['author'] ) ? '' : '1',
				'date'   => empty( $archives['date'] ) ? '' : '1',
				'tag'    => empty( $archives['tag'] ) ? '' : '1',
			)
		);
		update_option( 'acme_seo_og_enabled', empty( $post['og_enabled'] ) ? 'no' : 'yes' );
		update_option( 'acme_seo_og_default_image', absint( $post['og_default_image'] ?? 0 ) );
		update_option( 'acme_seo_twitter_handle', $post['twitter_handle'] ?? '' );
		update_option( 'acme_seo_social_profiles', isset( $post['social_profiles'] ) ? (array) $post['social_profiles'] : array() );
		update_option( 'acme_seo_sitemap_enabled', empty( $post['sitemap_enabled'] ) ? '0' : '1' );
		update_option( 'acme_seo_sitemap_exclude', $post['sitemap_exclude'] ?? '' );
		update_option( 'acme_seo_verification', isset( $post['verification'] ) ? (array) $post['verification'] : array() );
		// phpcs:enable

		Options::flush();

		/**
		 * Fires after the SEO settings were saved.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_seo_settings_saved' );

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'options-general.php?page=' . self::SLUG ) ) );
		exit;
	}
}
