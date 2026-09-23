/**
 * Front end: expand/collapse answers.
 */
import $ from 'jquery';

$( function () {
	$( '.acme-faq' ).each( function () {
		const $faq = $( this );
		$faq.find( '.acme-faq__answer' ).hide();

		if ( $faq.data( 'open-first' ) ) {
			const $first = $faq.find( '.acme-faq__item' ).first();
			$first.addClass( 'is-open' );
			$first.find( '.acme-faq__answer' ).show();
		}
	} );

	$( document ).on( 'click', '.acme-faq__question', function () {
		const $item = $( this ).closest( '.acme-faq__item' );
		$item.toggleClass( 'is-open' );
		$item.find( '.acme-faq__answer' ).first().slideToggle( 150 );
	} );
} );
