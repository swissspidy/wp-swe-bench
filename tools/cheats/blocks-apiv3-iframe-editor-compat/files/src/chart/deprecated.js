/**
 * Earlier saved formats of the chart block.
 */
import { RichText } from '@wordpress/block-editor';

/**
 * 1.0 – 1.2: <div> with data-series/data-height and an <h4> title.
 *
 * Saved with block API version 1: the wrapper classes (wp-block-acme-chart,
 * custom class names) and the anchor were added to the root element automatically.
 */
const v1 = {
	apiVersion: 1,
	attributes: {
		title: {
			type: 'string',
			source: 'html',
			selector: '.acme-chart__title',
		},
		series: {
			type: 'array',
			default: [],
		},
		height: {
			type: 'number',
			default: 240,
		},
	},
	supports: {
		html: false,
		anchor: true,
	},
	save( { attributes } ) {
		const { title, series, height } = attributes;
		return (
			<div
				className="acme-chart"
				data-series={ JSON.stringify( series ) }
				data-height={ height }
			>
				{ ! RichText.isEmpty( title ) && (
					<RichText.Content
						tagName="h4"
						className="acme-chart__title"
						value={ title }
					/>
				) }
				<div className="acme-chart__canvas" style={ { height } } />
			</div>
		);
	},
	migrate( attributes ) {
		return { ...attributes, showValues: false };
	},
};

export default [ v1 ];
