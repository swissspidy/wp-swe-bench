/**
 * Deprecated versions of the statistic.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { migrateStat } from '../shared/legacy';

/**
 * 1.5 – 1.6: bespoke colour + number size (48px unless changed), `is-boxed`.
 * `fontSize` has no default here so an unchanged size isn't converted.
 */
const v1 = {
	attributes: {
		value: {
			type: 'string',
			default: '',
		},
		label: {
			type: 'string',
			default: '',
		},
		alignment: {
			type: 'string',
			default: 'left',
		},
		color: {
			type: 'string',
		},
		fontSize: {
			type: 'number',
		},
		boxed: {
			type: 'boolean',
			default: false,
		},
	},
	supports: {
		html: false,
		anchor: true,
	},
	save( { attributes } ) {
		const { value, label, alignment, color, fontSize, boxed } = attributes;
		const classes = [];
		if ( boxed ) {
			classes.push( 'is-boxed' );
		}
		if ( alignment && alignment !== 'left' ) {
			classes.push( `has-text-align-${ alignment }` );
		}
		return (
			<div
				{ ...useBlockProps.save( {
					className: classes.join( ' ' ) || undefined,
					style: {
						color: color || undefined,
						fontSize: `${ fontSize ?? 48 }px`,
					},
				} ) }
			>
				<RichText.Content
					tagName="span"
					className="wp-block-acme-stat__value"
					value={ value }
				/>
				<RichText.Content
					tagName="span"
					className="wp-block-acme-stat__label"
					value={ label }
				/>
			</div>
		);
	},
	migrate: migrateStat,
};

export default [ v1 ];
