<?php
/**
 * Helpers for the product grid tests: parse server-rendered grids.
 */

namespace WPSB\Catalog;

/** Categories of the seeded products (fixture knowledge). */
const PRODUCTS = array(
	'Blue Mug'            => array( 'mugs' ),
	'Red Mug'             => array( 'mugs' ),
	'Café Mug'            => array( 'mugs' ),
	'Travel Mug'          => array( 'mugs' ),
	'Blue T-Shirt'        => array( 'shirts' ),
	'Logo T-Shirt'        => array( 'shirts' ),
	'Vintage T-Shirt'     => array( 'shirts' ),
	'Mountain Poster'     => array( 'posters' ),
	'Ocean Poster'        => array( 'posters' ),
	'Mug & Poster Bundle' => array( 'mugs', 'posters' ),
	'Sticker Pack'        => array( 'stickers' ),
	'Gift Card'           => array(),
);

function titles_in( string $category ): array {
	return array_keys( array_filter( PRODUCTS, static fn( $cats ) => '' === $category || in_array( $category, $cats, true ) ) );
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . ( false === stripos( $html, '<html' ) ? '<html><body>' . $html . '</body></html>' : $html ) );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class_xpath( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

function squish( string $s ): string {
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * All product grids of a page, as the server rendered them.
 *
 * @return array<int, array>
 */
function grids( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[' . has_class_xpath( 'wp-block-acme-product-grid' ) . ']' ) as $grid ) {
		$q       = static fn( string $class, $ctx = null ) => $xpath->query( './/*[' . has_class_xpath( $class ) . ']', $ctx ?? $grid );
		$filters = array();
		foreach ( $q( 'acme-grid__filter' ) as $b ) {
			$filters[] = array(
				'slug'    => $b->getAttribute( 'data-category' ),
				'text'    => squish( $b->textContent ),
				'pressed' => $b->hasAttribute( 'aria-pressed' ) ? $b->getAttribute( 'aria-pressed' ) : null,
				'active'  => in_array( 'is-active', preg_split( '/\s+/', $b->getAttribute( 'class' ) ), true ),
				'tag'     => strtolower( $b->nodeName ),
				'type'    => $b->getAttribute( 'type' ),
			);
		}
		$items = array();
		foreach ( $q( 'acme-grid__item' ) as $li ) {
			$title   = $q( 'acme-grid__title', $li )->item( 0 );
			$price   = $q( 'acme-grid__price', $li )->item( 0 );
			$link    = $xpath->query( './/a', $li )->item( 0 );
			$items[] = array(
				'title'  => $title ? squish( $title->textContent ) : '',
				'price'  => $price ? squish( $price->textContent ) : '',
				'href'   => $link ? $link->getAttribute( 'href' ) : '',
				'hidden' => $li->hasAttribute( 'hidden' ),
			);
		}
		$search  = $q( 'acme-grid__search' )->item( 0 );
		$count   = $q( 'acme-grid__count' )->item( 0 );
		$empty   = $q( 'acme-grid__empty' )->item( 0 );
		$heading = $q( 'acme-grid__heading' )->item( 0 );
		$nav     = $q( 'acme-grid__pagination' )->item( 0 );
		$page    = $q( 'acme-grid__page' )->item( 0 );
		$prev    = $q( 'acme-grid__prev' )->item( 0 );
		$next    = $q( 'acme-grid__next' )->item( 0 );
		$out[]   = array(
			'heading'      => $heading ? squish( $heading->textContent ) : null,
			'filters'      => $filters,
			'search'       => $search ? array( 'value' => $search->getAttribute( 'value' ) ) : null,
			'count'        => $count ? squish( $count->textContent ) : null,
			'items'        => $items,
			'visible'      => array_values( array_column( array_filter( $items, static fn( $i ) => ! $i['hidden'] ), 'title' ) ),
			'empty_hidden' => $empty ? $empty->hasAttribute( 'hidden' ) : null,
			'nav'          => $nav ? array(
				'hidden'        => $nav->hasAttribute( 'hidden' ),
				'text'          => $page ? squish( $page->textContent ) : null,
				'prev_disabled' => $prev ? $prev->hasAttribute( 'disabled' ) : null,
				'next_disabled' => $next ? $next->hasAttribute( 'disabled' ) : null,
			) : null,
		);
	}
	return $out;
}

/** [slug => pressed] of a grid. */
function pressed( array $grid ): array {
	return array_column( $grid['filters'], 'pressed', 'slug' );
}

/** Script elements of the page: [ [src, type], ... ]. */
function scripts( string $html ): array {
	$out = array();
	foreach ( dom( $html )->query( '//script' ) as $s ) {
		$out[] = array( 'src' => $s->getAttribute( 'src' ), 'type' => $s->getAttribute( 'type' ), 'text' => $s->textContent );
	}
	return $out;
}
