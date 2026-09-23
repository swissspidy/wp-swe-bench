/**
 * Event details block (dynamic, rendered by render.php).
 */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import metadata from './block.json';
import Edit from './edit';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
