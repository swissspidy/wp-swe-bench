/**
 * Recipe card block (dynamic).
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled, Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

function Edit( { context } ) {
	const blockProps = useBlockProps();
	const { postId, postType } = context;
	if ( postType !== 'acme_recipe' ) {
		return (
			<div { ...blockProps }>
				<Placeholder label={ __( 'Recipe card', 'acme-recipes' ) } instructions={ __( 'The recipe card is only shown on recipes.', 'acme-recipes' ) } />
			</div>
		);
	}
	return (
		<div { ...blockProps }>
			<Disabled>
				<ServerSideRender
					block={ metadata.name }
					urlQueryArgs={ { post_id: postId } }
					EmptyResponsePlaceholder={ () => (
						<Placeholder label={ __( 'Recipe card', 'acme-recipes' ) } instructions={ __( 'Add ingredients and times in the Recipe details panel and save.', 'acme-recipes' ) } />
					) }
				/>
			</Disabled>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
