<?php
/**
 * Course card used in the catalog and by the Course List block.
 *
 * @package Acme\Courses
 */

defined( 'ABSPATH' ) || exit;

$acme_course = Acme\Courses\Course::from_post( $args['post'] ?? null );
if ( ! $acme_course ) {
	return;
}
$acme_post = $acme_course->post();
?>
<li class="acme-course-card">
	<h2 class="acme-course-card__title">
		<a href="<?php echo esc_url( get_permalink( $acme_post ) ); ?>"><?php echo esc_html( get_the_title( $acme_post ) ); ?></a>
	</h2>
	<?php echo wp_kses_post( acme_courses_listing_meta_html( $acme_course ) ); ?>
	<?php if ( has_excerpt( $acme_post ) ) : ?>
		<div class="acme-course-card__excerpt"><?php echo wp_kses_post( wpautop( get_the_excerpt( $acme_post ) ) ); ?></div>
	<?php endif; ?>
</li>
