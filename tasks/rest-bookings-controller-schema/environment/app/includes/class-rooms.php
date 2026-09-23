<?php
/**
 * Rooms: the `acme_room` post type, its meta and the edit screen meta box.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Rooms.
 */
class Rooms {

	const POST_TYPE = 'acme_room';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
	}

	/**
	 * Post type. Exposed in the core REST API at /wp/v2/rooms.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Rooms', 'acme-bookings' ),
					'singular_name' => __( 'Room', 'acme-bookings' ),
					'add_new_item'  => __( 'Add New Room', 'acme-bookings' ),
					'edit_item'     => __( 'Edit Room', 'acme-bookings' ),
					'menu_name'     => __( 'Rooms', 'acme-bookings' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'rooms',
				'menu_icon'    => 'dashicons-building',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'rooms' ),
			)
		);
	}

	/**
	 * Room meta: capacity (guests) and nightly rate (major currency units).
	 */
	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			'acme_capacity',
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 2,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'can_edit_rooms' ),
			)
		);
		register_post_meta(
			self::POST_TYPE,
			'acme_rate',
			array(
				'type'              => 'number',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_rate' ),
				'auth_callback'     => array( $this, 'can_edit_rooms' ),
			)
		);
	}

	/**
	 * Meta auth callback.
	 *
	 * @return bool
	 */
	public function can_edit_rooms() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Rates are stored with two decimals ("129.00"); 1.0 stored "129,00" (comma).
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public static function sanitize_rate( $value ) {
		$value = str_replace( ',', '.', (string) $value );
		return round( max( 0, (float) $value ), 2 );
	}

	/**
	 * Nightly rate of a room.
	 *
	 * @param int $room_id Room ID.
	 * @return float
	 */
	public static function rate( $room_id ) {
		return self::sanitize_rate( get_post_meta( $room_id, 'acme_rate', true ) );
	}

	/**
	 * Maximum number of guests of a room.
	 *
	 * @param int $room_id Room ID.
	 * @return int
	 */
	public static function capacity( $room_id ) {
		$capacity = (int) get_post_meta( $room_id, 'acme_capacity', true );
		return $capacity > 0 ? $capacity : 2;
	}

	/**
	 * Whether the ID is a bookable room (published `acme_room`).
	 *
	 * @param int $room_id Room ID.
	 * @return bool
	 */
	public static function is_bookable( $room_id ) {
		$post = get_post( (int) $room_id );
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status;
	}

	/**
	 * Published rooms, for dropdowns.
	 *
	 * @return \WP_Post[]
	 */
	public static function all() {
		return get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
	}

	/**
	 * Meta box.
	 */
	public function add_meta_box() {
		add_meta_box( 'acme-room-details', __( 'Room details', 'acme-bookings' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'side' );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Room.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'acme_room_details', 'acme_room_details_nonce' );
		?>
		<p>
			<label for="acme-capacity"><?php esc_html_e( 'Capacity (guests)', 'acme-bookings' ); ?></label>
			<input type="number" min="1" id="acme-capacity" name="acme_capacity" value="<?php echo esc_attr( self::capacity( $post->ID ) ); ?>" />
		</p>
		<p>
			<label for="acme-rate"><?php esc_html_e( 'Rate per night', 'acme-bookings' ); ?></label>
			<input type="text" id="acme-rate" name="acme_rate" value="<?php echo esc_attr( number_format( self::rate( $post->ID ), 2, '.', '' ) ); ?>" />
			<?php echo esc_html( Pricing::currency() ); ?>
		</p>
		<?php
	}

	/**
	 * Save the meta box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['acme_room_details_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['acme_room_details_nonce'] ), 'acme_room_details' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['acme_capacity'] ) ) {
			update_post_meta( $post_id, 'acme_capacity', max( 1, absint( $_POST['acme_capacity'] ) ) );
		}
		if ( isset( $_POST['acme_rate'] ) ) {
			update_post_meta( $post_id, 'acme_rate', self::sanitize_rate( sanitize_text_field( wp_unslash( $_POST['acme_rate'] ) ) ) );
		}
	}
}
