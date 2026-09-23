/**
 * Editor preview for server-rendered newsroom blocks.
 */
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';

export default function ServerPreview( { name, attributes = {}, emptyLabel } ) {
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender
				block={ name }
				attributes={ attributes }
				EmptyResponsePlaceholder={ () => <p>{ emptyLabel }</p> }
			/>
		</div>
	);
}
