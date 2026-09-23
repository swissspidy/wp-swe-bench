<?php
/**
 * Notification email to the site owner.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the notification.
 */
class Mailer {

	/**
	 * Send the notification for a submission.
	 *
	 * @param array $submission {
	 *     @type array[] $fields     List of array( 'label' => string, 'value' => string ), in form order.
	 *     @type string  $reply_to   Sender email (optional).
	 *     @type string  $reply_name Sender name (optional).
	 *     @type string  $subject    Topic chosen by the sender (optional).
	 *     @type int     $post_id    Post the form was on.
	 * }
	 * @return bool
	 */
	public static function send( array $submission ) {
		$template = self::build( $submission );

		/**
		 * Filters the notification email. Sites use this to brand the subject, add footers
		 * or route messages to other people.
		 *
		 * @param array $template   to, subject, body (plain text), headers (string[]).
		 * @param array $submission The submission (see Mailer::send()).
		 */
		$template = apply_filters( 'acme_contact_email_template', $template, $submission );

		if ( empty( $template['to'] ) ) {
			return false;
		}
		return wp_mail( $template['to'], $template['subject'], $template['body'], $template['headers'] );
	}

	/**
	 * Default email.
	 *
	 * @param array $submission Submission.
	 * @return array{to:string, subject:string, body:string, headers:string[]}
	 */
	public static function build( array $submission ) {
		$settings = acme_contact_settings();
		$site     = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$topic    = ! empty( $submission['subject'] ) ? self::header_safe( $submission['subject'] ) : __( 'Contact form', 'acme-contact' );

		$lines = array();
		foreach ( (array) $submission['fields'] as $field ) {
			$lines[] = sprintf( '%s: %s', $field['label'], $field['value'] );
		}
		$post_id = isset( $submission['post_id'] ) ? (int) $submission['post_id'] : 0;
		if ( $post_id ) {
			$lines[] = '';
			$lines[] = '--';
			/* translators: 1: page title, 2: URL */
			$lines[] = sprintf( __( 'Sent from "%1$s" (%2$s)', 'acme-contact' ), wp_specialchars_decode( get_the_title( $post_id ), ENT_QUOTES ), get_permalink( $post_id ) );
		}

		$headers = array();
		$reply   = isset( $submission['reply_to'] ) ? sanitize_email( $submission['reply_to'] ) : '';
		if ( $reply && is_email( $reply ) ) {
			$name      = isset( $submission['reply_name'] ) ? self::header_safe( $submission['reply_name'] ) : '';
			$name      = str_replace( array( '"', '<', '>' ), '', $name );
			$headers[] = 'Reply-To: ' . ( '' !== $name ? sprintf( '"%s" <%s>', $name, $reply ) : $reply );
		}

		return array(
			'to'      => $settings['recipient'],
			/* translators: 1: site name, 2: topic */
			'subject' => sprintf( __( '[%1$s] New message: %2$s', 'acme-contact' ), $site, $topic ),
			'body'    => implode( "\n", $lines ),
			'headers' => $headers,
		);
	}

	/**
	 * Strip characters that could break out of an email header.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function header_safe( $value ) {
		return trim( preg_replace( '/[\r\n\t\0]+/', ' ', (string) $value ) );
	}
}
