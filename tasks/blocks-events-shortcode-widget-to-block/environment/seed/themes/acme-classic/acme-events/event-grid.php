<?php
/**
 * Acme Classic override of the Acme Events grid item: adds a "Details" call to action.
 *
 * @package AcmeClassic
 *
 * @var WP_Post $event
 * @var string  $date
 * @var string  $datetime
 * @var string  $venue
 * @var bool    $show_venue
 */

?>
<article class="acme-event acme-event--card acme-event--<?php echo (int) $event->ID; ?>">
	<h3 class="acme-event__heading"><a class="acme-event__link" href="<?php echo esc_url( get_permalink( $event ) ); ?>"><?php echo esc_html( get_the_title( $event ) ); ?></a></h3>
	<time class="acme-event__date" datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date ); ?></time>
	<?php if ( $show_venue && '' !== $venue ) : ?>
	<span class="acme-event__venue"><?php echo esc_html( $venue ); ?></span>
	<?php endif; ?>
	<a class="acme-event__cta" href="<?php echo esc_url( get_permalink( $event ) ); ?>">Details</a>
</article>
