<?php
/**
 * Helpers for the contact form tests: parse forms like a browser, submit them, read entries.
 */

namespace WPSB\Contact;

const SUCCESS = 'Thanks! We will get back to you within one business day.';

function page_id( string $slug ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page','post') ORDER BY ID LIMIT 1", $slug ) );
	if ( ! $id ) {
		throw new \RuntimeException( "seeded post $slug missing" );
	}
	return $id;
}

function entries_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_contact_entries';
}

/** All entries (fields decoded), oldest first. */
function entries(): array {
	global $wpdb;
	$wpdb->flush();
	$rows = (array) $wpdb->get_results( 'SELECT * FROM ' . entries_table() . ' ORDER BY id ASC', ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$row['fields'] = json_decode( (string) $row['fields'], true );
		$out[]         = $row;
	}
	return $out;
}

function entry_count(): int {
	global $wpdb;
	$wpdb->flush();
	$n = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . entries_table() );
	return null === $n ? -1 : (int) $n;
}

function xpath( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function cls( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

/**
 * The block form containing the control `acme_fields[<field>]` whose hidden inputs identify `$form_id`
 * (or simply the n-th block form on the page).
 */
function block_forms( string $html ): array {
	$x = xpath( $html );
	return array( $x, iterator_to_array( $x->query( '//form[' . cls( 'wp-block-acme-contact-form' ) . ']' ) ) );
}

/**
 * Parse a form like a browser would submit it: action + all successful controls (name => value).
 * Unchecked checkboxes are left out, selects send their selected (or first) option.
 */
function form_data( \DOMXPath $x, \DOMElement $form ): array {
	$data = array();
	foreach ( $x->query( './/input[@name]', $form ) as $input ) {
		$type = strtolower( $input->getAttribute( 'type' ) );
		if ( in_array( $type, array( 'submit', 'button', 'image', 'reset' ), true ) ) {
			continue;
		}
		if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $input->hasAttribute( 'checked' ) ) {
			continue;
		}
		$data[ $input->getAttribute( 'name' ) ] = $input->hasAttribute( 'value' ) ? $input->getAttribute( 'value' ) : ( 'checkbox' === $type ? 'on' : '' );
	}
	foreach ( $x->query( './/textarea[@name]', $form ) as $ta ) {
		$data[ $ta->getAttribute( 'name' ) ] = $ta->textContent;
	}
	foreach ( $x->query( './/select[@name]', $form ) as $select ) {
		$selected = $x->query( './/option[@selected]', $select );
		$first    = $x->query( './/option', $select );
		$option   = $selected->length ? $selected->item( 0 ) : ( $first->length ? $first->item( 0 ) : null );
		$data[ $select->getAttribute( 'name' ) ] = $option ? ( $option->hasAttribute( 'value' ) ? $option->getAttribute( 'value' ) : trim( $option->textContent ) ) : '';
	}
	return $data;
}

/** Value that a checked checkbox of the form would send. */
function checkbox_value( \DOMXPath $x, \DOMElement $form, string $name ): string {
	$box = $x->query( './/input[@type="checkbox"][@name="' . $name . '"]', $form );
	if ( ! $box->length ) {
		throw new \RuntimeException( "no checkbox $name" );
	}
	$box = $box->item( 0 );
	return $box->hasAttribute( 'value' ) ? $box->getAttribute( 'value' ) : 'on';
}

/** The control for a field name inside a form. */
function control( \DOMXPath $x, \DOMElement $form, string $field ): ?\DOMElement {
	$nodes = $x->query( './/*[@name="acme_fields[' . $field . ']"]', $form );
	return $nodes->length ? $nodes->item( 0 ) : null;
}

/** Text of the elements an aria-describedby points to. */
function described_by( \DOMXPath $x, \DOMElement $control ): string {
	$text = '';
	foreach ( preg_split( '/\s+/', trim( $control->getAttribute( 'aria-describedby' ) ) ) as $id ) {
		if ( '' === $id ) {
			continue;
		}
		$el = $x->query( '//*[@id="' . $id . '"]' );
		if ( $el->length ) {
			$text .= ' ' . trim( preg_replace( '/\s+/', ' ', $el->item( 0 )->textContent ) );
		}
	}
	return trim( $text );
}

/** Text of role=status / role=alert elements inside a form. */
function role_text( \DOMXPath $x, \DOMElement $form, string $role ): string {
	$text = '';
	foreach ( $x->query( './/*[@role="' . $role . '"]', $form ) as $el ) {
		$text .= ' ' . trim( preg_replace( '/\s+/', ' ', $el->textContent ) );
	}
	return trim( $text );
}

/** Set-Cookie header → "a=b; c=d". */
function cookies_from( array $res ): string {
	if ( empty( $res['headers']['set-cookie'] ) ) {
		return '';
	}
	$out = array();
	foreach ( preg_split( '/,\s*(?=[^;,=\s]+=)/', $res['headers']['set-cookie'] ) as $cookie ) {
		$out[] = trim( explode( ';', $cookie )[0] );
	}
	return implode( '; ', $out );
}
