/**
 * Notice tones, as registered on the server (`acme_content_blocks_tones`).
 */
import { __ } from '@wordpress/i18n';

const FALLBACK = [
	{ value: 'info', label: __( 'Info', 'acme-content-blocks' ) },
	{ value: 'success', label: __( 'Success', 'acme-content-blocks' ) },
	{ value: 'warning', label: __( 'Warning', 'acme-content-blocks' ) },
	{ value: 'error', label: __( 'Error', 'acme-content-blocks' ) },
];

export function getTones() {
	const fromServer = window.acmeContentBlocks?.tones;
	return Array.isArray( fromServer ) && fromServer.length
		? fromServer
		: FALLBACK;
}
