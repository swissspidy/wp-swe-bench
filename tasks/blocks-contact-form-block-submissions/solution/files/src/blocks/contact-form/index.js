/**
 * Contact form block.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import transforms from './transforms';
import './style.scss';
import './editor.scss';

registerBlockType( metadata, {
	edit: Edit,
	// Only the fields are stored (as block comments); the form is rendered on the server.
	save: () => <InnerBlocks.Content />,
	transforms,
} );
