<?php
/**
 * Renders a preview card.
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
		$theme = $prefs['theme'];

		ob_start();
		?>
		<div class="acme-lp-card acme-lp-<?php echo $theme; ?>">
			<?php if ( ! empty( $prefs['show_images'] ) && ! empty( $preview['image'] ) ) : ?>
				<img class="acme-lp-image" src="<?php echo $preview['image']; ?>" alt="" />
			<?php endif; ?>
			<a class="acme-lp-title" href="<?php echo $preview['url']; ?>"><?php echo $preview['title']; ?></a>
			<p class="acme-lp-desc"><?php echo $preview['description']; ?></p>
		</div>
		<?php
		return trim( ob_get_clean() );
	}
}
