<?php
/**
 * [acme_listing_search]: the search form and results on the "Find a home" page.
 *
 * The form submits with GET: min_price, max_price, beds, city, features[], status,
 * sort and listing_page (the results page).
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Search form shortcode.
 */
class Shortcode {

	const TAG = 'acme_listing_search';

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Search arguments from the query string.
	 *
	 * @return array
	 */
	public static function args_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public search form.
		$args = array();
		foreach ( array( 'min_price', 'max_price', 'beds', 'city', 'status', 'sort' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		if ( isset( $_GET['features'] ) ) {
			$args['features'] = array_map( 'sanitize_key', (array) wp_unslash( $_GET['features'] ) );
		}
		if ( isset( $_GET['listing_page'] ) ) {
			$args['page'] = absint( $_GET['listing_page'] );
		}
		// phpcs:enable
		return $args;
	}

	/**
	 * Render form + results.
	 *
	 * @param array|string $atts Attributes: per_page.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'per_page' => Search::DEFAULT_PER_PAGE ), $atts, self::TAG );
		wp_enqueue_style( 'acme-re-search' );

		$args             = self::args_from_request();
		$args['per_page'] = max( 1, (int) $atts['per_page'] );
		$result           = acme_re_search( $args );
		$parsed           = $result['args'];

		ob_start();
		$this->form( $parsed );

		echo '<div class="acme-re-results-wrap">';
		printf(
			'<p class="acme-re-count">%s</p>',
			esc_html(
				/* translators: %s: number of listings */
				sprintf( _n( '%s listing found', '%s listings found', $result['total'], 'acme-real-estate' ), number_format_i18n( $result['total'] ) )
			)
		);

		if ( $result['ids'] ) {
			echo '<ul class="acme-re-results">';
			foreach ( $result['ids'] as $id ) {
				$listing = Listing::get( $id );
				if ( ! $listing ) {
					continue;
				}
				$this->card( $listing );
			}
			echo '</ul>';
			$this->pagination( $result );
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * One result.
	 *
	 * @param Listing $listing Listing.
	 */
	private function card( Listing $listing ) {
		$beds = $listing->bedrooms();
		printf(
			'<li class="acme-re-listing is-%1$s" data-listing-id="%2$d"><a class="acme-re-listing__title" href="%3$s">%4$s</a> <span class="acme-re-listing__price">%5$s</span> <span class="acme-re-listing__city">%6$s</span>%7$s</li>',
			esc_attr( $listing->status() ),
			(int) $listing->id(),
			esc_url( get_permalink( $listing->post() ) ),
			esc_html( get_the_title( $listing->post() ) ),
			esc_html( $listing->price_label() ),
			esc_html( $listing->city() ),
			null === $beds ? '' : sprintf(
				' <span class="acme-re-listing__beds">%s</span>',
				/* translators: %d: bedrooms */
				esc_html( sprintf( _n( '%d bedroom', '%d bedrooms', $beds, 'acme-real-estate' ), $beds ) )
			)
		);
	}

	/**
	 * Pagination links.
	 *
	 * @param array $result Search result.
	 */
	private function pagination( array $result ) {
		if ( $result['pages'] < 2 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'    => add_query_arg( 'listing_page', '%#%' ),
				'format'  => '',
				'current' => $result['page'],
				'total'   => $result['pages'],
				'type'    => 'list',
			)
		);
		echo '<nav class="acme-re-pagination">' . wp_kses_post( $links ) . '</nav>';
	}

	/**
	 * Search form.
	 *
	 * @param array $args Parsed search args.
	 */
	private function form( array $args ) {
		?>
		<form class="acme-re-search" method="get" action="<?php echo esc_url( get_permalink() ); ?>">
			<label><?php esc_html_e( 'Min price', 'acme-real-estate' ); ?>
				<input type="text" name="min_price" value="<?php echo $args['min_price'] ? esc_attr( $args['min_price'] ) : ''; ?>" inputmode="numeric" />
			</label>
			<label><?php esc_html_e( 'Max price', 'acme-real-estate' ); ?>
				<input type="text" name="max_price" value="<?php echo $args['max_price'] ? esc_attr( $args['max_price'] ) : ''; ?>" inputmode="numeric" />
			</label>
			<label><?php esc_html_e( 'Bedrooms', 'acme-real-estate' ); ?>
				<select name="beds">
					<option value=""><?php esc_html_e( 'Any', 'acme-real-estate' ); ?></option>
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<option value="<?php echo (int) $i; ?>" <?php selected( $args['beds'], $i ); ?>><?php echo esc_html( $i . '+' ); ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label><?php esc_html_e( 'City', 'acme-real-estate' ); ?>
				<select name="city">
					<option value=""><?php esc_html_e( 'Anywhere', 'acme-real-estate' ); ?></option>
					<?php foreach ( Listing::cities() as $city ) : ?>
						<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $args['city'], $city ); ?>><?php echo esc_html( $city ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<fieldset class="acme-re-search__features">
				<legend><?php esc_html_e( 'Features', 'acme-real-estate' ); ?></legend>
				<?php foreach ( Features::all() as $slug => $label ) : ?>
					<label><input type="checkbox" name="features[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $args['features'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<label><?php esc_html_e( 'Sort by', 'acme-real-estate' ); ?>
				<select name="sort">
					<?php foreach ( Search::sorts() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $args['sort'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit"><?php esc_html_e( 'Search', 'acme-real-estate' ); ?></button>
		</form>
		<?php
	}
}
