/**
 * A single question of the FAQ block.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	deprecated,
} );
