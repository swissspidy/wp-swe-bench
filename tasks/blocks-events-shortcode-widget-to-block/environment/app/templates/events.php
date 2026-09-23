<?php
/**
 * Event listing wrapper.
 *
 * Override in your theme: `{theme}/acme-events/events.php`.
 *
 * @package Acme\Events
 *
 * @var string[] $items   Rendered items.
 * @var string   $layout  "list" or "grid".
 * @var string   $title   Optional heading.
 * @var string   $empty   Message when there are no events.
 * @var array    $options Normalized listing options.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="acme-events acme-events--<?php echo esc_attr( $layout ); ?>">
<?php if ( '' !== $title ) : ?>
	<h2 class="acme-events__title"><?php echo esc_html( $title ); ?></h2>
<?php endif; ?>
<?php if ( $items ) : ?>
	<?php if ( 'grid' === $layout ) : ?>
	<div class="acme-events__grid">
		<?php echo implode( "\n", $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the item templates. ?>
	</div>
	<?php else : ?>
	<ul class="acme-events__items">
		<?php echo implode( "\n", $items ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the item templates. ?>
	</ul>
	<?php endif; ?>
<?php else : ?>
	<p class="acme-events__empty"><?php echo esc_html( $empty ); ?></p>
<?php endif; ?>
</div>
