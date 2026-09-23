<?php
/**
 * Plugin Name: Acme Newsletter
 * Description: Newsletter sign-up box ([acme_newsletter]) and FAQ teaser ([acme_faq_teaser]). Built on the Acme UI Kit runtime.
 * Version: 1.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * The sign-up box has its own UI Kit component ("newsletter") and uses the kit's tabs.
 * We load the kit and register our component early, on pages that show the box.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! has_shortcode( $post->post_content, 'acme_newsletter' ) || ! function_exists( 'acme_ui_enqueue' ) ) {
			return;
		}
		acme_ui_enqueue( array( 'tabs' ) );
		wp_add_inline_script(
			'acme-ui-core',
			'AcmeUI.register( "newsletter", function ( el ) {
				el.classList.add( "is-ready" );
				el.querySelector( ".acme-newsletter__status" ).textContent = "Ready to subscribe";
				el.querySelector( "form" ).addEventListener( "submit", function ( e ) {
					e.preventDefault();
					el.querySelector( ".acme-newsletter__status" ).textContent = "Thanks, " + el.querySelector( "input[type=email]" ).value;
				} );
			} );',
			'after'
		);
	}
);

add_shortcode(
	'acme_newsletter',
	static function () {
		return '<div class="acme-newsletter" data-acme-component="newsletter">
			<p class="acme-newsletter__status">Loading…</p>
			<div class="acme-tabs acme-newsletter__tabs" data-acme-component="tabs">
				<div class="acme-tabs__list" role="tablist">
					<button type="button" role="tab" class="acme-tabs__tab is-active" id="nl-tab-0" aria-controls="nl-panel-0" aria-selected="true" tabindex="0">Weekly</button>
					<button type="button" role="tab" class="acme-tabs__tab" id="nl-tab-1" aria-controls="nl-panel-1" aria-selected="false" tabindex="-1">Monthly</button>
				</div>
				<div role="tabpanel" class="acme-tabs__panel" id="nl-panel-0" aria-labelledby="nl-tab-0"><p>Every Friday: new trails and gear.</p></div>
				<div role="tabpanel" class="acme-tabs__panel" id="nl-panel-1" aria-labelledby="nl-tab-1" hidden><p>Once a month: the best of the month.</p></div>
			</div>
			<form><input type="email" name="email" value="hiker@example.org" aria-label="Email"><button type="submit">Subscribe</button></form>
		</div>';
	}
);

/**
 * FAQ teaser: accordion markup printed by us; the kit is requested while rendering.
 */
add_shortcode(
	'acme_faq_teaser',
	static function () {
		if ( function_exists( 'acme_ui_enqueue' ) ) {
			acme_ui_enqueue( array( 'accordion' ) );
		}
		return '<div class="acme-accordion acme-faq-teaser" data-acme-component="accordion" data-single="false">
			<div class="acme-accordion__item"><h3 class="acme-accordion__heading"><button type="button" class="acme-accordion__toggle" id="faq-toggle-0" aria-expanded="false" aria-controls="faq-panel-0">Do you ship abroad?<span class="acme-icon acme-icon--chevron" aria-hidden="true"></span></button></h3>
			<div class="acme-accordion__panel" id="faq-panel-0" role="region" aria-labelledby="faq-toggle-0" hidden><p>Yes, to 30 countries.</p></div></div>
		</div>';
	}
);
