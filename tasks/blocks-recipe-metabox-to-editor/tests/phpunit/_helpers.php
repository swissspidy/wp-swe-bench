<?php
/**
 * Helpers for the recipe tests.
 */

namespace WPSB\Recipes;

function recipe_id( string $slug ): int {
	$posts = get_posts(
		array(
			'name'        => $slug,
			'post_type'   => 'acme_recipe',
			'post_status' => 'any',
			'numberposts' => 1,
		)
	);
	if ( ! $posts ) {
		throw new \RuntimeException( "Seeded recipe '$slug' not found" );
	}
	return $posts[0]->ID;
}

function user_id( string $login ): int {
	$user = get_user_by( 'login', $login );
	if ( ! $user ) {
		throw new \RuntimeException( "Seeded user '$login' not found" );
	}
	return $user->ID;
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

/** Recipe cards in some HTML: list of [meta => [class => text], ingredients => [[amount, item]]]. */
function cards( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' acme-recipe-card ')]" ) as $card ) {
		$meta = array();
		foreach ( $xpath->query( ".//*[contains(@class, 'acme-recipe-card__meta')]/li", $card ) as $li ) {
			$span                                  = $xpath->query( './/span', $li )->item( 0 );
			$meta[ trim( $li->getAttribute( 'class' ) ) ] = $span ? trim( $span->textContent ) : '';
		}
		$ingredients = array();
		foreach ( $xpath->query( ".//*[contains(@class, 'acme-recipe-card__ingredient ') or @class='acme-recipe-card__ingredient']", $card ) as $li ) {
			$ingredients[] = array(
				trim( $xpath->query( ".//*[contains(@class, 'acme-recipe-card__amount')]", $li )->item( 0 )->textContent ?? '' ),
				trim( $xpath->query( ".//*[contains(@class, 'acme-recipe-card__item')]", $li )->item( 0 )->textContent ?? '' ),
			);
		}
		$out[] = array(
			'meta'        => $meta,
			'ingredients' => $ingredients,
			'text'        => $card->textContent,
			'node'        => $card,
		);
	}
	return $out;
}
