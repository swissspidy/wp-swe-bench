/**
 * Find the chart blocks of the current post that a legend can point to.
 */
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

/**
 * Chart blocks (with an HTML anchor) in the post being edited.
 *
 * @return {Array} Chart blocks.
 */
export function useCharts() {
	return useSelect( ( select ) => {
		const { getClientIdsWithDescendants, getBlock } = select( blockEditorStore );
		return getClientIdsWithDescendants()
			.map( ( id ) => getBlock( id ) )
			.filter( ( block ) => block && block.name === 'acme/chart' && block.attributes.anchor );
	}, [] );
}
