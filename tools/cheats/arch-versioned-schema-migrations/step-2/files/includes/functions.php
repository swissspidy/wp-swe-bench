<?php
/**
 * Public API for add-ons (Acme CRM Pro, the invoicing plugin, theme templates).
 * Keep these stable.
 *
 * @package Acme\CRM
 */

defined( 'ABSPATH' ) || exit;

use Acme\CRM\Contacts;
use Acme\CRM\Installer;
use Acme\CRM\Stages;

/**
 * Full table name.
 *
 * @param string $name 'contacts' or 'notes'.
 * @return string
 */
function acme_crm_table( $name ) {
	return Installer::table( $name );
}

/**
 * A contact as an array: id, full_name ("first last", kept for older add-ons), first_name,
 * last_name, email, phone, company, stage, owner_id, source, created_at, updated_at.
 * Null when it does not exist.
 *
 * @param int $id Contact ID.
 * @return array|null
 */
function acme_crm_get_contact( $id ) {
	return Contacts::to_array( Contacts::find( (int) $id ) );
}

/**
 * Create a contact (same keys as acme_crm_get_contact(); pass first_name/last_name, or a
 * legacy full_name that is split into first and last name).
 *
 * @param array $data Contact fields.
 * @return int|WP_Error
 */
function acme_crm_create_contact( array $data ) {
	return Contacts::create( $data );
}

/**
 * Lifecycle stages (slug => label).
 *
 * @return array<string,string>
 */
function acme_crm_stages() {
	return Stages::all();
}
