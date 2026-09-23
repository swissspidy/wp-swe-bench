/**
 * Saved markup: an empty list pointing at the chart. The items are rendered by
 * assets/js/chart-renderer.js from the chart's data.
 */
import { useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { chartId, layout } = attributes;
	return (
		<ul
			{ ...useBlockProps.save( {
				className: 'vertical' === layout ? 'acme-legend is-vertical' : 'acme-legend',
				'data-chart': chartId || undefined,
			} ) }
		/>
	);
}
