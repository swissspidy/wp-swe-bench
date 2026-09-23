<?php
/**
 * Orders synced from the POS / online shop, with order notes.
 *
 * Orders are private `acme_order` posts. Everything else lives in post meta:
 *
 * | key                       | format                                                              |
 * |---------------------------|---------------------------------------------------------------------|
 * | _acme_order_number        | "AC-1042"                                                           |
 * | _acme_order_status        | processing, completed, cancelled, refunded                          |
 * | _acme_order_customer_id   | user ID, 0 for guest orders                                         |
 * | _acme_order_email         | email given at checkout (as typed, guests are identified by it)     |
 * | _acme_order_total         | decimal string                                                      |
 * | _acme_order_notes         | list of notes, see below (since 2.0)                                |
 * | _acme_order_note          | 1.x: the customer's delivery note as a single string                |
 *
 * A note is array( 'date' => UTC Y-m-d H:i:s, 'author' => 'customer'|'staff', 'staff_id' => int,
 * 'visible' => bool (staff notes shown to the customer), 'text' => string ).
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Order post type, meta helpers and the order screen.
 */
class Orders {

	const POST_TYPE = 'acme_order';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_staff_note' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'acme_loyalty_order_status_changed', array( $this, 'maybe_award_points' ), 10, 2 );
	}

	/**
	 * Register the order post type.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Orders', 'acme-loyalty' ),
					'singular_name' => __( 'Order', 'acme-loyalty' ),
					'edit_item'     => __( 'Order details', 'acme-loyalty' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'acme-loyalty',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Order statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return array(
			'processing' => __( 'Processing', 'acme-loyalty' ),
			'completed'  => __( 'Completed', 'acme-loyalty' ),
			'cancelled'  => __( 'Cancelled', 'acme-loyalty' ),
			'refunded'   => __( 'Refunded', 'acme-loyalty' ),
		);
	}

	/**
	 * Create an order (used by the POS sync and the CLI import).
	 *
	 * @param array $data number, status, customer_id, email, total, date (UTC), note (customer note).
	 * @return int|\WP_Error Order post ID.
	 */
	public static function create( array $data ) {
		$data = wp_parse_args(
			$data,
			array(
				'number'      => '',
				'status'      => 'processing',
				'customer_id' => 0,
				'email'       => '',
				'total'       => '0.00',
				'date'        => current_time( 'mysql', true ),
				'note'        => '',
			)
		);
		$id   = wp_insert_post(
			array(
				'post_type'     => self::POST_TYPE,
				'post_status'   => 'private',
				'post_title'    => $data['number'],
				'post_date_gmt' => $data['date'],
				'post_date'     => get_date_from_gmt( $data['date'] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_acme_order_number', sanitize_text_field( $data['number'] ) );
		update_post_meta( $id, '_acme_order_status', array_key_exists( $data['status'], self::statuses() ) ? $data['status'] : 'processing' );
		update_post_meta( $id, '_acme_order_customer_id', (int) $data['customer_id'] );
		update_post_meta( $id, '_acme_order_email', sanitize_email( $data['email'] ) );
		update_post_meta( $id, '_acme_order_total', number_format( (float) $data['total'], 2, '.', '' ) );
		if ( '' !== $data['note'] ) {
			self::add_note( $id, $data['note'], 'customer' );
		}
		if ( 'completed' === $data['status'] ) {
			Ledger::award_for_order( $id );
		}
		return $id;
	}

	/**
	 * Order number ("AC-1042"), falls back to the post ID.
	 *
	 * @param int $order_id Order post ID.
	 * @return string
	 */
	public static function number( $order_id ) {
		$number = (string) get_post_meta( $order_id, '_acme_order_number', true );
		return '' !== $number ? $number : '#' . (int) $order_id;
	}

	/**
	 * Status slug.
	 *
	 * @param int $order_id Order post ID.
	 * @return string
	 */
	public static function status( $order_id ) {
		$status = (string) get_post_meta( $order_id, '_acme_order_status', true );
		return '' !== $status ? $status : 'processing';
	}

	/**
	 * Change the status of an order.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $status   New status.
	 */
	public static function set_status( $order_id, $status ) {
		$old = self::status( $order_id );
		if ( $old === $status || ! array_key_exists( $status, self::statuses() ) ) {
			return;
		}
		update_post_meta( $order_id, '_acme_order_status', $status );

		/**
		 * Fires when an order changes status.
		 *
		 * @param int    $order_id Order post ID.
		 * @param string $status   New status.
		 * @param string $old      Previous status.
		 */
		do_action( 'acme_loyalty_order_status_changed', $order_id, $status, $old );
	}

	/**
	 * All notes of an order, oldest first (includes the 1.x single delivery note).
	 *
	 * @param int $order_id Order post ID.
	 * @return array[]
	 */
	public static function get_notes( $order_id ) {
		$notes = get_post_meta( $order_id, '_acme_order_notes', true );
		$notes = is_array( $notes ) ? array_values( $notes ) : array();

		$legacy = (string) get_post_meta( $order_id, '_acme_order_note', true );
		if ( '' !== $legacy ) {
			array_unshift(
				$notes,
				array(
					'date'     => get_post_field( 'post_date_gmt', $order_id ),
					'author'   => 'customer',
					'staff_id' => 0,
					'visible'  => true,
					'text'     => $legacy,
				)
			);
		}
		return $notes;
	}

	/**
	 * Append a note.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $text     Note text.
	 * @param string $author   'customer' or 'staff'.
	 * @param bool   $visible  Staff notes: visible to the customer?
	 */
	public static function add_note( $order_id, $text, $author = 'staff', $visible = false ) {
		$notes   = get_post_meta( $order_id, '_acme_order_notes', true );
		$notes   = is_array( $notes ) ? $notes : array();
		$notes[] = array(
			'date'     => current_time( 'mysql', true ),
			'author'   => 'customer' === $author ? 'customer' : 'staff',
			'staff_id' => 'customer' === $author ? 0 : get_current_user_id(),
			'visible'  => 'customer' === $author ? true : (bool) $visible,
			'text'     => sanitize_textarea_field( $text ),
		);
		update_post_meta( $order_id, '_acme_order_notes', $notes );
	}

	/**
	 * Award points when an order is completed.
	 *
	 * @param int    $order_id Order post ID.
	 * @param string $status   New status.
	 */
	public function maybe_award_points( $order_id, $status ) {
		if ( 'completed' === $status ) {
			Ledger::award_for_order( $order_id );
		}
	}

	/**
	 * Order details + notes meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'acme-order-details', __( 'Order', 'acme-loyalty' ), array( $this, 'render_details' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'acme-order-notes', __( 'Notes', 'acme-loyalty' ), array( $this, 'render_notes' ), self::POST_TYPE, 'side' );
	}

	/**
	 * Details meta box.
	 *
	 * @param \WP_Post $post Order.
	 */
	public function render_details( $post ) {
		$customer = (int) get_post_meta( $post->ID, '_acme_order_customer_id', true );
		$user     = $customer ? get_userdata( $customer ) : null;
		$statuses = self::statuses();
		?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Order number', 'acme-loyalty' ); ?></th><td><?php echo esc_html( self::number( $post->ID ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Status', 'acme-loyalty' ); ?></th><td><?php echo esc_html( $statuses[ self::status( $post->ID ) ] ?? '' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Customer', 'acme-loyalty' ); ?></th><td><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Guest', 'acme-loyalty' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Email', 'acme-loyalty' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $post->ID, '_acme_order_email', true ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Total', 'acme-loyalty' ); ?></th><td><?php echo esc_html( (string) get_post_meta( $post->ID, '_acme_order_total', true ) ); ?></td></tr>
		</table>
		<?php
	}

	/**
	 * Notes meta box (+ add staff note).
	 *
	 * @param \WP_Post $post Order.
	 */
	public function render_notes( $post ) {
		wp_nonce_field( 'acme_order_note_' . $post->ID, 'acme_order_note_nonce' );
		echo '<ul class="acme-order-notes">';
		foreach ( self::get_notes( $post->ID ) as $note ) {
			printf(
				'<li class="acme-order-note acme-order-note--%1$s"><p>%2$s</p><small>%3$s</small></li>',
				esc_attr( $note['author'] ),
				esc_html( $note['text'] ),
				esc_html( acme_loyalty_format_date( $note['date'] ) )
			);
		}
		echo '</ul>';
		?>
		<p><textarea name="acme_order_note" rows="3" class="widefat"></textarea></p>
		<p><label><input type="checkbox" name="acme_order_note_visible" value="1" /> <?php esc_html_e( 'Visible to the customer', 'acme-loyalty' ); ?></label></p>
		<?php
	}

	/**
	 * Save a staff note from the notes meta box.
	 *
	 * @param int      $post_id Order.
	 * @param \WP_Post $post    Order post.
	 */
	public function save_staff_note( $post_id, $post ) {
		if ( ! isset( $_POST['acme_order_note_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['acme_order_note_nonce'] ), 'acme_order_note_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || empty( $_POST['acme_order_note'] ) ) {
			return;
		}
		self::add_note( $post_id, sanitize_textarea_field( wp_unslash( $_POST['acme_order_note'] ) ), 'staff', ! empty( $_POST['acme_order_note_visible'] ) );
	}

	/**
	 * List table columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		return array(
			'cb'                => $columns['cb'],
			'title'             => __( 'Order', 'acme-loyalty' ),
			'acme_order_status' => __( 'Status', 'acme-loyalty' ),
			'acme_order_email'  => __( 'Email', 'acme-loyalty' ),
			'acme_order_total'  => __( 'Total', 'acme-loyalty' ),
			'date'              => $columns['date'],
		);
	}

	/**
	 * List table column values.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Order.
	 */
	public function column( $column, $post_id ) {
		switch ( $column ) {
			case 'acme_order_status':
				$statuses = self::statuses();
				echo esc_html( $statuses[ self::status( $post_id ) ] ?? '' );
				break;
			case 'acme_order_email':
				echo esc_html( (string) get_post_meta( $post_id, '_acme_order_email', true ) );
				break;
			case 'acme_order_total':
				echo esc_html( (string) get_post_meta( $post_id, '_acme_order_total', true ) );
				break;
		}
	}
}
