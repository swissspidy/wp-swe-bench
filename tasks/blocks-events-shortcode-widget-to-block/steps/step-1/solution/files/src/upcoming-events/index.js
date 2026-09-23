/**
 * Upcoming Events block (dynamic: rendered on the server with the shortcode's templates).
 */
import { registerBlockType } from '@wordpress/blocks';

import './editor.scss';

import metadata from './block.json';
import Edit from './edit';
import transforms from './transforms';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
	transforms,
} );
