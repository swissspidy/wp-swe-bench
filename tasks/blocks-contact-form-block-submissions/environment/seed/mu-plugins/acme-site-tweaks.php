<?php
/**
 * Plugin Name: Acme site tweaks
 * Description: Site-specific customizations of the Acme Web Studio site.
 */

// Brand the contact notification emails.
add_filter(
	'acme_contact_email_template',
	static function ( $template ) {
		$template['subject'] = '[Acme Web] ' . $template['subject'];
		$template['body']   .= "\n\nAcme Web Studio · Contact form";
		return $template;
	}
);
