/**
 * Pricing table block (container of pricing plans).
 */
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

import './style.scss';
import './editor.scss';

import metadata from './block.json';
import Edit from './edit';
import deprecated from './deprecated';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
	deprecated,
} );
