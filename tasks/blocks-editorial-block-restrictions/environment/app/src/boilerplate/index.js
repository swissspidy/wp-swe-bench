import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import ServerPreview from '../shared/server-preview';

registerBlockType( metadata.name, {
	edit: () => (
		<ServerPreview
			name={ metadata.name }
			emptyLabel={ __( 'Fill in Settings → Newsroom to show this block.', 'acme-newsroom' ) }
		/>
	),
	save: () => null,
} );
