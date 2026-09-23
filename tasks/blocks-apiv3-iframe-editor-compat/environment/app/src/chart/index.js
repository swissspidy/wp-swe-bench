/**
 * Bar chart block.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import transforms from './transforms';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	deprecated,
	transforms,
} );
