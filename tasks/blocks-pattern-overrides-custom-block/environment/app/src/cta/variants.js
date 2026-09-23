/**
 * CTA variants, as registered on the server (`acme_cta_variants` filter).
 */
import { __ } from '@wordpress/i18n';

const FALLBACK = [
	{ value: 'primary', label: __( 'Primary', 'acme-cta' ) },
	{ value: 'secondary', label: __( 'Secondary', 'acme-cta' ) },
	{ value: 'dark', label: __( 'Dark', 'acme-cta' ) },
];

export function getVariants() {
	const fromServer = window.acmeCta?.variants;
	return Array.isArray( fromServer ) && fromServer.length
		? fromServer
		: FALLBACK;
}

export function isTrackingEnabled() {
	return !! window.acmeCta?.trackingEnabled;
}
