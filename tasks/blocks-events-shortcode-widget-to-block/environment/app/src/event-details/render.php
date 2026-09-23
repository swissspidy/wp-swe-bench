<?php
/**
 * Server rendering of the Event details block.
 *
 * @package Acme\Events
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$acme_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
if ( ! $acme_post_id || 'acme_event' !== get_post_type( $acme_post_id ) ) {
	return;
}

$acme_when = acme_events_format_date( $acme_post_id );
$acme_end  = (string) get_post_meta( $acme_post_id, ACME_EVENTS_META_END, true );
if ( ! empty( $attributes['showEnd'] ) && '' !== $acme_end ) {
	$acme_end_date = date_create_immutable_from_format( 'Y-m-d H:i', $acme_end, wp_timezone() );
	if ( $acme_end_date ) {
		$acme_when .= ' – ' . wp_date( get_option( 'time_format' ), $acme_end_date->getTimestamp() );
	}
}
$acme_venue = acme_events_get_venue( $acme_post_id );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'acme-event-details' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="acme-event-details__when"><?php echo esc_html( $acme_when ); ?></p>
	<?php if ( '' !== $acme_venue ) : ?>
	<p class="acme-event-details__where"><?php echo esc_html( $acme_venue ); ?></p>
	<?php endif; ?>
</div>
