<?php
/**
 * "Related reading" box on the post edit screen.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Meta box for editor picks and per-post switches.
 */
class Metabox {

	/** @var Engine */
	private $engine;

	/**
	 * Constructor.
	 *
	 * @param Engine $engine Engine.
	 */
	public function __construct( Engine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes_post', array( $this, 'add' ) );
		add_action( 'save_post_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the box.
	 */
	public function add() {
		add_meta_box( 'acme-related', __( 'Related reading', 'acme-related' ), array( $this, 'render' ), 'post', 'side' );
	}

	/**
	 * Box content.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render( $post ) {
		wp_nonce_field( 'acme_related_save', 'acme_related_nonce' );
		$picks   = Engine::parse_manual( get_post_meta( $post->ID, Engine::META_MANUAL, true ) );
		$exclude = Engine::is_excluded( $post->ID );
		$hide    = $this->engine->is_hidden_for( $post->ID );
		$primary = (int) get_post_meta( $post->ID, Item::META_PRIMARY_CATEGORY, true );
		?>
		<p>
			<label for="acme-related-manual"><?php esc_html_e( 'Editor picks (post IDs, comma separated)', 'acme-related' ); ?></label>
			<input type="text" class="widefat" id="acme-related-manual" name="acme_related_manual" value="<?php echo esc_attr( implode( ', ', $picks ) ); ?>" />
		</p>
		<p>
			<label for="acme-related-primary"><?php esc_html_e( 'Primary category', 'acme-related' ); ?></label>
			<select class="widefat" id="acme-related-primary" name="acme_related_primary">
				<option value="0"><?php esc_html_e( '(first category)', 'acme-related' ); ?></option>
				<?php foreach ( get_the_category( $post->ID ) as $category ) : ?>
					<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $primary, $category->term_id ); ?>><?php echo esc_html( $category->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><label><input type="checkbox" name="acme_related_exclude" value="1" <?php checked( $exclude ); ?> /> <?php esc_html_e( 'Never show this post as related reading', 'acme-related' ); ?></label></p>
		<p><label><input type="checkbox" name="acme_related_hide" value="1" <?php checked( $hide ); ?> /> <?php esc_html_e( 'No related list under this post', 'acme-related' ); ?></label></p>
		<?php
	}

	/**
	 * Save the box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['acme_related_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['acme_related_nonce'] ), 'acme_related_save' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$picks = Engine::parse_manual( isset( $_POST['acme_related_manual'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_related_manual'] ) ) : '' );
		if ( $picks ) {
			update_post_meta( $post_id, Engine::META_MANUAL, $picks );
		} else {
			delete_post_meta( $post_id, Engine::META_MANUAL );
		}

		$primary = isset( $_POST['acme_related_primary'] ) ? absint( $_POST['acme_related_primary'] ) : 0;
		if ( $primary ) {
			update_post_meta( $post_id, Item::META_PRIMARY_CATEGORY, $primary );
		} else {
			delete_post_meta( $post_id, Item::META_PRIMARY_CATEGORY );
		}

		foreach ( array(
			'acme_related_exclude' => Engine::META_EXCLUDE,
			'acme_related_hide'    => Engine::META_HIDE,
		) as $field => $key ) {
			if ( ! empty( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $key, '1' );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}

		// Reading time, stored since 2.0.
		update_post_meta( $post_id, '_acme_reading_time', acme_related_estimate_reading_time( $post->post_content ) );
	}
}
