/**
 * Chart legend block.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './toggle';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
