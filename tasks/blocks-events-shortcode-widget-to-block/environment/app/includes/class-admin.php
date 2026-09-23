<?php
/**
 * Admin: event details meta box and list table columns.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Event details meta box ("When & where") and the "Starts" column.
 */
class Admin {

	const NONCE_ACTION = 'acme_events_save_details';
	const NONCE_NAME   = 'acme_events_details_nonce';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_start' ) );
	}

	/**
	 * Add the meta box.
	 */
	public function add_meta_box() {
		add_meta_box( 'acme-event-details', __( 'When & where', 'acme-events' ), array( $this, 'render_meta_box' ), null, 'side', 'high' );
	}

	/**
	 * Meta box markup.
	 *
	 * @param \WP_Post $post Event.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$start = (string) get_post_meta( $post->ID, ACME_EVENTS_META_START, true );
		$end   = (string) get_post_meta( $post->ID, ACME_EVENTS_META_END, true );
		$venue = acme_events_get_venue( $post );
		?>
		<p>
			<label for="acme-event-start"><?php esc_html_e( 'Starts', 'acme-events' ); ?></label><br />
			<input type="datetime-local" id="acme-event-start" name="acme_event_start" value="<?php echo esc_attr( str_replace( ' ', 'T', $start ) ); ?>" />
		</p>
		<p>
			<label for="acme-event-end"><?php esc_html_e( 'Ends', 'acme-events' ); ?></label><br />
			<input type="datetime-local" id="acme-event-end" name="acme_event_end" value="<?php echo esc_attr( str_replace( ' ', 'T', $end ) ); ?>" />
		</p>
		<p>
			<label for="acme-event-venue"><?php esc_html_e( 'Venue', 'acme-events' ); ?></label><br />
			<input type="text" class="widefat" id="acme-event-venue" name="acme_event_venue" value="<?php echo esc_attr( $venue ); ?>" />
		</p>
		<?php
	}

	/**
	 * Save the meta box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( array(
			'acme_event_start' => ACME_EVENTS_META_START,
			'acme_event_end'   => ACME_EVENTS_META_END,
		) as $field => $key ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			$value = self::normalize_datetime( $value );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
		$venue = isset( $_POST['acme_event_venue'] ) ? sanitize_text_field( wp_unslash( $_POST['acme_event_venue'] ) ) : '';
		update_post_meta( $post_id, ACME_EVENTS_META_VENUE, $venue );
	}

	/**
	 * Normalize "2031-10-01T18:00" (datetime-local) to "2031-10-01 18:00".
	 *
	 * @param string $value Raw value.
	 * @return string Normalized value or '' when invalid.
	 */
	public static function normalize_datetime( $value ) {
		$value = str_replace( 'T', ' ', trim( $value ) );
		$date  = date_create_immutable_from_format( 'Y-m-d H:i', substr( $value, 0, 16 ), wp_timezone() );
		return $date ? $date->format( 'Y-m-d H:i' ) : '';
	}

	/**
	 * Add the "Starts" column.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['acme_event_start'] = __( 'Starts', 'acme-events' );
				$new['acme_event_venue'] = __( 'Venue', 'acme-events' );
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ) {
		if ( 'acme_event_start' === $column ) {
			echo esc_html( acme_events_format_date( $post_id ) );
		} elseif ( 'acme_event_venue' === $column ) {
			echo esc_html( acme_events_get_venue( $post_id ) );
		}
	}

	/**
	 * Make "Starts" sortable.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function sortable_columns( $columns ) {
		$columns['acme_event_start'] = 'acme_event_start';
		return $columns;
	}

	/**
	 * Sort the admin list by start date.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function sort_by_start( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'acme_event_start' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', ACME_EVENTS_META_START );
		$query->set( 'orderby', 'meta_value' );
	}
}
