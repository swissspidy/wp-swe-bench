<?php
/**
 * Classic "Upcoming events" widget.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Lists upcoming events in a sidebar.
 *
 * Settings: title, number of events (1–10), one event category (stored as term ID; 0 = all)
 * and whether to show the venue.
 */
class Upcoming_Events_Widget extends \WP_Widget {

	const ID_BASE = 'acme_upcoming_events';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			self::ID_BASE,
			__( 'Upcoming Events (Acme)', 'acme-events' ),
			array(
				'classname'                   => 'widget_acme_upcoming_events',
				'description'                 => __( 'A list of upcoming events.', 'acme-events' ),
				'customize_selective_refresh' => true,
				'show_instance_in_rest'       => true,
			)
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'title'      => __( 'Upcoming events', 'acme-events' ),
			'count'      => 5,
			'category'   => 0,
			'show_venue' => true,
		);
	}

	/**
	 * Front-end output.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Settings.
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, self::defaults() );
		$title    = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$category = '';
		if ( ! empty( $instance['category'] ) ) {
			$term = get_term( (int) $instance['category'], Post_Type::TAXONOMY );
			if ( $term instanceof \WP_Term ) {
				$category = $term->slug;
			}
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- sidebar markup comes from the theme.
		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}
		echo Plugin::instance()->shortcode->render(
			array(
				'limit'      => max( 1, min( 10, (int) $instance['count'] ) ),
				'category'   => $category,
				'show_venue' => $instance['show_venue'] ? 'yes' : 'no',
			)
		);
		echo $args['after_widget'];
		// phpcs:enable
	}

	/**
	 * Settings form.
	 *
	 * @param array $instance Settings.
	 * @return string
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, self::defaults() );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'acme-events' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of events:', 'acme-events' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" min="1" max="10" step="1" value="<?php echo esc_attr( $instance['count'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>"><?php esc_html_e( 'Category:', 'acme-events' ); ?></label>
			<?php
			wp_dropdown_categories(
				array(
					'taxonomy'        => Post_Type::TAXONOMY,
					'name'            => $this->get_field_name( 'category' ),
					'id'              => $this->get_field_id( 'category' ),
					'selected'        => (int) $instance['category'],
					'show_option_all' => __( 'All categories', 'acme-events' ),
					'hide_empty'      => false,
					'class'           => 'widefat',
				)
			);
			?>
		</p>
		<p>
			<input class="checkbox" type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_venue' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_venue' ) ); ?>" <?php checked( (bool) $instance['show_venue'] ); ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_venue' ) ); ?>"><?php esc_html_e( 'Show venue', 'acme-events' ); ?></label>
		</p>
		<?php
		return '';
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'      => sanitize_text_field( $new_instance['title'] ?? '' ),
			'count'      => max( 1, min( 10, absint( $new_instance['count'] ?? 5 ) ) ),
			'category'   => absint( $new_instance['category'] ?? 0 ),
			'show_venue' => ! empty( $new_instance['show_venue'] ),
		);
	}
}
