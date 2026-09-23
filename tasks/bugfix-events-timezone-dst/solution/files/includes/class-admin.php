<?php
/**
 * Event editor (meta box) and admin list columns.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
class Admin {

	const NONCE_ACTION = 'acme_event_save';
	const NONCE_NAME   = 'acme_event_nonce';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . Post_Type::NAME, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Post_Type::NAME, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . Post_Type::NAME . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::NAME . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::NAME . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_by_start' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Adds the "Date & time" meta box.
	 */
	public function add_meta_box() {
		add_meta_box( 'acme-event-dates', __( 'Date & time', 'acme-events' ), array( $this, 'render_meta_box' ), Post_Type::NAME, 'side', 'high' );
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		$event   = Event::get( $post );
		$start   = $event && $event->has_dates() ? $event->get_start_local() : '';
		$end     = $event && $event->has_dates() ? $event->get_end_local() : '';
		$all_day = $event && $event->is_all_day();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<p>
			<label for="acme-event-start-date"><?php esc_html_e( 'Starts', 'acme-events' ); ?></label><br />
			<input type="date" id="acme-event-start-date" name="acme_event_start_date" value="<?php echo esc_attr( Dates::date_part( $start ) ); ?>" />
			<input type="time" id="acme-event-start-time" name="acme_event_start_time" value="<?php echo esc_attr( $start ? substr( $start, 11, 5 ) : '' ); ?>" />
		</p>
		<p>
			<label for="acme-event-end-date"><?php esc_html_e( 'Ends', 'acme-events' ); ?></label><br />
			<input type="date" id="acme-event-end-date" name="acme_event_end_date" value="<?php echo esc_attr( Dates::date_part( $end ) ); ?>" />
			<input type="time" id="acme-event-end-time" name="acme_event_end_time" value="<?php echo esc_attr( $end && ! $all_day ? substr( $end, 11, 5 ) : '' ); ?>" />
		</p>
		<p>
			<label>
				<input type="checkbox" name="acme_event_all_day" value="1" <?php checked( $all_day ); ?> />
				<?php esc_html_e( 'All-day event', 'acme-events' ); ?>
			</label>
		</p>
		<p>
			<label for="acme-event-location"><?php esc_html_e( 'Location', 'acme-events' ); ?></label><br />
			<input type="text" class="widefat" id="acme-event-location" name="acme_event_location" value="<?php echo esc_attr( $event ? $event->get_location() : '' ); ?>" />
		</p>
		<p class="description">
			<?php
			if ( $event && $event->has_timezone() ) {
				/* translators: %s: timezone name */
				printf( esc_html__( 'Times are in the event timezone (%s).', 'acme-events' ), esc_html( $event->get_timezone_string() ) );
			} else {
				/* translators: %s: timezone name */
				printf( esc_html__( 'Times are in the site timezone (%s).', 'acme-events' ), esc_html( wp_timezone_string() ) );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Saves the meta box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$field = static function ( $name ) {
			return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		};

		$all_day    = '1' === $field( 'acme_event_all_day' );
		$start_date = $field( 'acme_event_start_date' );
		$end_date   = $field( 'acme_event_end_date' );
		$start      = $all_day ? $start_date : trim( $start_date . ' ' . $field( 'acme_event_start_time' ) );
		$end        = $all_day ? $end_date : trim( ( $end_date ? $end_date : $start_date ) . ' ' . $field( 'acme_event_end_time' ) );

		update_post_meta( $post_id, '_acme_event_location', $field( 'acme_event_location' ) );

		if ( '' === $start_date ) {
			return;
		}

		$result = Event::get( $post )->save_dates( $start, $end, $all_day );
		if ( is_wp_error( $result ) ) {
			set_transient( 'acme_events_error_' . get_current_user_id(), $result->get_error_message(), 60 );
		}
	}

	/**
	 * Shows validation errors after saving.
	 */
	public function notices() {
		$key     = 'acme_events_error_' . get_current_user_id();
		$message = get_transient( $key );
		if ( $message ) {
			delete_transient( $key );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * List table columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['acme_event_start'] = __( 'Starts', 'acme-events' );
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
	public function column( $column, $post_id ) {
		if ( 'acme_event_start' !== $column ) {
			return;
		}
		$event = Event::get( $post_id );
		if ( ! $event || ! $event->has_dates() ) {
			echo '&mdash;';
			return;
		}
		echo esc_html( Frontend::format_start( $event ) );
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['acme_event_start'] = 'acme_event_start';
		return $columns;
	}

	/**
	 * Sorts the admin list by start.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function sort_by_start( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'acme_event_start' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', '_acme_event_start_utc' );
		$query->set( 'orderby', 'meta_value' );
	}
}
