/**
 * Transforms: a core table with two columns (label, value) becomes a chart.
 */
import { createBlock } from '@wordpress/blocks';

const stripTags = ( html ) => String( html || '' ).replace( /<[^>]*>/g, '' ).trim();

export default {
	from: [
		{
			type: 'block',
			blocks: [ 'core/table' ],
			isMatch: ( { body } ) =>
				Array.isArray( body ) &&
				body.length > 0 &&
				body.every( ( row ) => row.cells && row.cells.length >= 2 ),
			transform: ( { body, caption } ) =>
				createBlock( 'acme/chart', {
					title: caption || '',
					series: body
						.map( ( row ) => ( {
							label: stripTags( row.cells[ 0 ].content ),
							value: parseFloat( stripTags( row.cells[ 1 ].content ) ) || 0,
						} ) )
						.filter( ( point ) => point.label ),
				} ),
		},
	],
};
