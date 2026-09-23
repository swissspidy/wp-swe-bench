<?php
/**
 * Helpers for the pricing table tests: render seeded content and inspect tables.
 */

namespace WPSB\Pricing;

/** Render a seeded post's content through the_content, as the front end would. */
function render_slug( string $slug, string $post_type = 'page' ): string {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return (string) $html;
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function cls( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

/**
 * Tables in document order with their plans.
 *
 * @return array<int, array{classes:string[], nested:bool, plans:array}>
 */
function tables( string $html ): array {
	$xp  = dom( $html );
	$out = array();
	foreach ( $xp->query( '//*[' . cls( 'wp-block-acme-pricing-table' ) . ']' ) as $table ) {
		$plans = array();
		foreach ( $xp->query( './/*[' . cls( 'acme-pricing__plan' ) . ']', $table ) as $plan ) {
			$q       = static function ( string $c ) use ( $xp, $plan ) {
				$n = $xp->query( './/*[' . cls( $c ) . ']', $plan )->item( 0 );
				return $n ? trim( preg_replace( '/\s+/u', ' ', str_replace( array( "\u{00A0}", "\u{2019}", "\u{2018}" ), array( ' ', "'", "'" ), $n->textContent ) ) ) : null;
			};
			$plans[] = array(
				'classes'  => preg_split( '/\s+/', trim( $plan->getAttribute( 'class' ) ) ),
				'name'     => $q( 'acme-pricing__name' ),
				'amount'   => $q( 'acme-pricing__amount' ),
				'period'   => $q( 'acme-pricing__period' ),
				'features' => array_map( static fn( $li ) => trim( $li->textContent ), iterator_to_array( $xp->query( './/*[' . cls( 'acme-pricing__features' ) . ']//li', $plan ) ) ),
			);
		}
		$nested = $xp->query( 'ancestor::*[' . cls( 'wp-block-acme-pricing-table' ) . ']', $table )->length > 0;
		$out[]  = array(
			'classes' => preg_split( '/\s+/', trim( $table->getAttribute( 'class' ) ) ),
			'nested'  => $nested,
			'plans'   => $plans,
		);
	}
	return $out;
}

/** Whether a plan element is marked as the highlighted/featured one (1.x or 2.0 class names). */
function is_marked( array $plan ): bool {
	return (bool) array_intersect( $plan['classes'], array( 'is-featured', 'is-highlighted', 'acme-pricing__plan--featured' ) );
}

/** JSON-LD Product objects in a served page. */
function json_ld( string $html ): array {
	preg_match_all( '#<script[^>]+type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m );
	$out = array();
	foreach ( $m[1] as $json ) {
		$data = json_decode( $json, true );
		if ( is_array( $data ) && ( $data['@type'] ?? '' ) === 'Product' ) {
			$out[] = $data;
		}
	}
	return $out;
}
