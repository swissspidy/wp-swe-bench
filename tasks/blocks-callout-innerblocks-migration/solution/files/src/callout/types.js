/**
 * Callout types, provided by PHP (see includes/class-block.php).
 */
import { __ } from '@wordpress/i18n';

const FALLBACK = [
	{ value: 'info', label: __( 'Info', 'acme-callouts' ) },
	{ value: 'success', label: __( 'Success', 'acme-callouts' ) },
	{ value: 'warning', label: __( 'Warning', 'acme-callouts' ) },
	{ value: 'danger', label: __( 'Danger', 'acme-callouts' ) },
];

export function getTypes() {
	return window.acmeCallouts?.types?.length
		? window.acmeCallouts.types
		: FALLBACK;
}

export function getDefaultType() {
	return window.acmeCallouts?.defaultType || 'info';
}

export function getTypeLabel( type ) {
	const match = getTypes().find( ( option ) => option.value === type );
	return match ? match.label : getTypes()[ 0 ].label;
}
