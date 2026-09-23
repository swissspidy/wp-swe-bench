<?php
/**
 * A single event in the "grid" layout.
 *
 * Override in your theme: `{theme}/acme-events/event-grid.php`.
 *
 * @package Acme\Events
 *
 * @var WP_Post $event      The event.
 * @var string  $date       Formatted start date.
 * @var string  $datetime   Machine readable start date.
 * @var string  $venue      Venue.
 * @var bool    $show_venue Whether to show the venue.
 */

defined( 'ABSPATH' ) || exit;
?>
<article class="acme-event acme-event--card acme-event--<?php echo (int) $event->ID; ?>">
	<?php if ( has_post_thumbnail( $event ) ) : ?>
	<div class="acme-event__image"><?php echo get_the_post_thumbnail( $event, 'medium' ); ?></div>
	<?php endif; ?>
	<h3 class="acme-event__heading"><a class="acme-event__link" href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a></h3>
	<time class="acme-event__date" datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date ); ?></time>
	<?php if ( $show_venue && '' !== $venue ) : ?>
	<span class="acme-event__venue"><?php echo esc_html( $venue ); ?></span>
	<?php endif; ?>
</article>
