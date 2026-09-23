<?php
/**
 * Helper functions.
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin options merged with defaults.
 *
 * @return array{structured_data:bool}
 */
function get_options() {
	$options = get_option( 'acme_faq_options', array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	return array_merge( array( 'structured_data' => true ), $options );
}

/**
 * Anchor (HTML id) of a question.
 *
 * The structured data links every question to `{permalink}#{anchor}`, so this
 * must stay stable: "How do I reset my password?" → "faq-how-do-i-reset-my-password".
 *
 * @param string $question Question (may contain inline HTML).
 * @return string
 */
function question_anchor( $question ) {
	$slug = sanitize_title( wp_strip_all_tags( html_entity_decode( (string) $question, ENT_QUOTES, 'UTF-8' ) ) );
	return 'faq-' . ( '' === $slug ? 'question' : $slug );
}

/**
 * Plain text of an HTML fragment (for structured data).
 *
 * @param string $html HTML.
 * @return string
 */
function plain_text( $html ) {
	$text = wp_strip_all_tags( (string) $html );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}
