<?php
/**
 * Plugin Name: Acme site tweaks (team pronouns)
 * Description: Adds a "Pronouns" field to team members.
 */

add_filter(
	'acme_team_fields',
	static function ( $fields ) {
		$fields['pronouns'] = array(
			'label'    => 'Pronouns',
			'type'     => 'text',
			'meta_key' => '_acme_pronouns',
		);
		return $fields;
	}
);
