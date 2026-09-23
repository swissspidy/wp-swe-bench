import { __ } from '@wordpress/i18n';

export const FIELD_BLOCKS = [ 'acme/field-text', 'acme/field-email', 'acme/field-textarea', 'acme/field-select', 'acme/field-checkbox' ];

export const DEFAULT_FIELDS = [
	[ 'acme/field-text', { name: 'name', label: __( 'Name', 'acme-contact' ), required: true } ],
	[ 'acme/field-email', { name: 'email', label: __( 'Email', 'acme-contact' ), required: true } ],
	[ 'acme/field-textarea', { name: 'message', label: __( 'Message', 'acme-contact' ), required: true } ],
];

/**
 * A new random form ID.
 *
 * @return {string} ID.
 */
export function newFormId() {
	return 'form-' + Math.random().toString( 36 ).slice( 2, 10 );
}
