<?php
/**
 * Helpers for the newsletter tests: parse served pages and find the signup forms.
 */

namespace WPSB\Newsletter;

const OPTION = 'acme_newsletter_settings';

/** The option as seeded (2.3.1 format, auto_insert on). */
function legacy_settings( array $overrides = array() ): array {
	return array_merge(
		array(
			'heading'         => 'Get the Acme newsletter',
			'description'     => 'Product news and event invitations, once a week.',
			'button_label'    => 'Subscribe',
			'consent_text'    => 'I agree to receive the Acme newsletter.',
			'success_message' => 'Thanks! Please check your inbox to confirm your subscription.',
			'show_name'       => true,
			'auto_insert'     => true,
		),
		$overrides
	);
}

/** Settings in the new format with the given placements. */
function settings_with_placements( array $placements ): array {
	$s = legacy_settings();
	unset( $s['auto_insert'] );
	$s['placements'] = $placements;
	return $s;
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

function has_class_el( \DOMElement $el, string $class ): bool {
	return false !== strpos( ' ' . preg_replace( '/\s+/', ' ', $el->getAttribute( 'class' ) ) . ' ', ' ' . $class . ' ' );
}

function next_element( \DOMNode $node ): ?\DOMElement {
	for ( $n = $node->nextSibling; $n; $n = $n->nextSibling ) {
		if ( XML_ELEMENT_NODE === $n->nodeType ) {
			return $n;
		}
	}
	return null;
}

function prev_element( \DOMNode $node ): ?\DOMElement {
	for ( $n = $node->previousSibling; $n; $n = $n->previousSibling ) {
		if ( XML_ELEMENT_NODE === $n->nodeType ) {
			return $n;
		}
	}
	return null;
}

function last_element_child( \DOMNode $node ): ?\DOMElement {
	for ( $n = $node->lastChild; $n; $n = $n->previousSibling ) {
		if ( XML_ELEMENT_NODE === $n->nodeType ) {
			return $n;
		}
	}
	return null;
}

function contains_node( \DOMNode $ancestor, \DOMNode $node ): bool {
	for ( $n = $node; $n; $n = $n->parentNode ) {
		if ( $n->isSameNode( $ancestor ) ) {
			return true;
		}
	}
	return false;
}

/** Closest ancestor (or self) with a class. */
function closest( \DOMNode $node, string $class ): ?\DOMElement {
	for ( $n = $node; $n && XML_ELEMENT_NODE === $n->nodeType; $n = $n->parentNode ) {
		if ( has_class_el( $n, $class ) ) {
			return $n;
		}
	}
	return null;
}

/**
 * Analyse a served page.
 *
 * @return array{
 *   xpath: \DOMXPath,
 *   forms: \DOMElement[],
 *   post_content: ?\DOMElement,
 *   in_content: \DOMElement[],
 *   after_content: \DOMElement[],
 *   before_content: \DOMElement[],
 *   in_footer: \DOMElement[],
 *   footer: ?\DOMElement
 * }
 */
function analyse( string $html ): array {
	$xpath = dom( $html );
	$forms = iterator_to_array( $xpath->query( '//form[' . has_class( 'acme-newsletter__form' ) . ']' ) );
	$pc    = $xpath->query( '//*[' . has_class( 'wp-block-post-content' ) . ']' )->item( 0 );
	if ( ! $pc ) {
		$pc = $xpath->query( '//*[' . has_class( 'entry-content' ) . ']' )->item( 0 );
	}
	$footer = $xpath->query( '//footer[' . has_class( 'wp-block-template-part' ) . ']' )->item( 0 );

	$out = array(
		'xpath'          => $xpath,
		'forms'          => $forms,
		'post_content'   => $pc,
		'in_content'     => array(),
		'after_content'  => array(),
		'before_content' => array(),
		'in_footer'      => array(),
		'footer'         => $footer,
	);
	foreach ( $forms as $form ) {
		$wrapper = closest( $form, 'acme-newsletter' ) ?? $form;
		if ( $pc && contains_node( $pc, $form ) ) {
			$out['in_content'][] = $form;
		}
		if ( $footer && contains_node( $footer, $form ) ) {
			$out['in_footer'][] = $form;
		}
		if ( $pc ) {
			$next = next_element( $pc );
			if ( $next && ( $next->isSameNode( $wrapper ) || contains_node( $next, $form ) ) ) {
				$out['after_content'][] = $form;
			}
			$prev = prev_element( $pc );
			if ( $prev && ( $prev->isSameNode( $wrapper ) || contains_node( $prev, $form ) ) ) {
				$out['before_content'][] = $form;
			}
		}
	}
	return $out;
}

/** Hidden + visible field values of a form (name => value). */
function form_fields( \DOMElement $form ): array {
	$fields = array();
	foreach ( ( new \DOMXPath( $form->ownerDocument ) )->query( './/input', $form ) as $input ) {
		$name = $input->getAttribute( 'name' );
		if ( '' === $name ) {
			continue;
		}
		$type = strtolower( $input->getAttribute( 'type' ) );
		if ( in_array( $type, array( 'checkbox', 'radio' ), true ) ) {
			continue;
		}
		$fields[ $name ] = $input->getAttribute( 'value' );
	}
	return $fields;
}

function field_value( \DOMElement $form, string $name ): ?string {
	$fields = form_fields( $form );
	return $fields[ $name ] ?? null;
}

/** Subscriber row (fresh from the DB). */
function subscriber( string $email ): ?object {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}acme_newsletter_subscribers WHERE email = %s", $email ) );
}

function delete_subscriber( string $email ): void {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'acme_newsletter_subscribers', array( 'email' => $email ) );
}

function post_id( string $slug, string $type = 'post' ): int {
	$p = get_page_by_path( $slug, OBJECT, $type );
	if ( ! $p ) {
		throw new \RuntimeException( "Seeded $type '$slug' not found" );
	}
	return (int) $p->ID;
}
