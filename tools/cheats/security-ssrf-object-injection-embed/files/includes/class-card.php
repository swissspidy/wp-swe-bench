<?php
/**
 * Renders a preview card. Every value that ends up in the markup is escaped, and
 * URLs are limited to safe protocols.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Preview card renderer.
 */
class Card {

	/**
	 * Render a preview as HTML.
	 *
	 * @param array      $preview Preview data (url, title, description, image).
	 * @param array|null $prefs   Visitor preferences; defaults to the current visitor's.
	 * @return string
	 */
	public static function render( array $preview, $prefs = null ) {
		if ( ! is_array( $prefs ) ) {
			$prefs = Prefs::current();
		}
		$theme = 'dark' === $prefs['theme'] ? 'dark' : 'light';

		$url   = esc_url( isset( $preview['url'] ) ? $preview['url'] : '' );
		$title = esc_html( isset( $preview['title'] ) ? $preview['title'] : '' );
		$desc  = esc_html( isset( $preview['description'] ) ? $preview['description'] : '' );
		$image = isset( $preview['image'] ) ? esc_url( $preview['image'], array( 'http', 'https' ) ) : '';

		ob_start();
		?>
		<div class="acme-lp-card acme-lp-<?php echo esc_attr( $theme ); ?>">
			<?php if ( ! empty( $prefs['show_images'] ) && '' !== $image ) : ?>
				<img class="acme-lp-image" src="<?php echo $image; ?>" alt="" />
			<?php endif; ?>
			<a class="acme-lp-title" href="<?php echo $url; ?>"><?php echo $title; ?></a>
			<p class="acme-lp-desc"><?php echo $desc; ?></p>
		</div>
		<?php
		return trim( ob_get_clean() );
	}
}
