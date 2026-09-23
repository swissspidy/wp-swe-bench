/**
 * The "Glossary term" format type.
 */
import { __ } from '@wordpress/i18n';

import Edit from './edit';

export const FORMAT_NAME = 'acme/glossary-term';
export const FORMAT_CLASS = 'acme-glossary-term';

export const settings = {
	title: __( 'Glossary term', 'acme-glossary' ),
	tagName: 'span',
	className: FORMAT_CLASS,
	attributes: {
		termId: 'data-term-id',
	},
	interactive: false,
	edit: Edit,
};
