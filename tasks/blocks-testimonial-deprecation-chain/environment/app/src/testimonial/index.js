/**
 * Testimonial block.
 */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';
import './editor.scss';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	deprecated,
} );
