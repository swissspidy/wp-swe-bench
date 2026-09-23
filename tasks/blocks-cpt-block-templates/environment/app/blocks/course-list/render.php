<?php
/**
 * Server render of the Course List block.
 *
 * @package Acme\Courses
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$acme_args = array(
	'post_type'      => Acme\Courses\Post_Types::POST_TYPE,
	'post_status'    => 'publish',
	'posts_per_page' => max( 1, min( 24, (int) ( $attributes['number'] ?? 6 ) ) ),
	'orderby'        => in_array( $attributes['orderby'] ?? 'date', array( 'date', 'title', 'menu_order' ), true ) ? $attributes['orderby'] : 'date',
	'order'          => 'date' === ( $attributes['orderby'] ?? 'date' ) ? 'DESC' : 'ASC',
	'no_found_rows'  => true,
);
if ( ! empty( $attributes['topic'] ) ) {
	$acme_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		array(
			'taxonomy' => Acme\Courses\Post_Types::TAXONOMY,
			'field'    => 'slug',
			'terms'    => sanitize_title( $attributes['topic'] ),
		),
	);
}
$acme_query = new WP_Query( $acme_args );

if ( ! $acme_query->have_posts() ) {
	printf( '<div %s><p>%s</p></div>', get_block_wrapper_attributes(), esc_html__( 'No courses found.', 'acme-courses' ) );
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<ul class="acme-course-grid">
		<?php
		foreach ( $acme_query->posts as $acme_post ) {
			acme_courses_get_template_part( 'course-card', array( 'post' => $acme_post ) );
		}
		?>
	</ul>
</div>
