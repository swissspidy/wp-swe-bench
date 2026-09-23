/**
 * "Glossary term" text format + conversion of legacy [glossary] shortcodes.
 */
import { registerFormatType } from '@wordpress/rich-text';
import { addFilter } from '@wordpress/hooks';

import { FORMAT_NAME, settings } from './format';
import { addShortcodeConversion } from './convert';

registerFormatType( FORMAT_NAME, settings );

addFilter(
	'blocks.registerBlockType',
	'acme-glossary/convert-shortcodes',
	addShortcodeConversion
);
