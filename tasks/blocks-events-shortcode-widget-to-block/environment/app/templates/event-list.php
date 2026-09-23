<?php
/**
 * A single event in the "list" layout.
 *
 * Override in your theme: `{theme}/acme-events/event-list.php`.
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
<li class="acme-event acme-event--<?php echo (int) $event->ID; ?>">
	<time class="acme-event__date" datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date ); ?></time>
	<a class="acme-event__link" href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a>
	<?php if ( $show_venue && '' !== $venue ) : ?>
	<span class="acme-event__venue"><?php echo esc_html( $venue ); ?></span>
	<?php endif; ?>
</li>
