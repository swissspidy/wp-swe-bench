/**
 * Deprecated versions of the notice box.
 *
 * The bespoke attributes are declared without defaults on purpose: a value
 * that is undefined was never chosen by an editor and must not be converted
 * (save() still prints the historical defaults so the old markup validates).
 */
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

import { migrateNotice } from '../shared/legacy';

const legacyAttributes = {
	tone: {
		type: 'string',
		default: 'info',
	},
	bgColor: {
		type: 'string',
	},
	textColor: {
		type: 'string',
	},
	padding: {
		type: 'number',
	},
	fontSize: {
		type: 'number',
	},
};

function legacyStyle( { bgColor, textColor, padding, fontSize } ) {
	const style = {};
	if ( bgColor ) {
		style.backgroundColor = bgColor;
	}
	if ( textColor ) {
		style.color = textColor;
	}
	if ( padding ) {
		style.padding = `${ padding }px`;
	}
	if ( fontSize ) {
		style.fontSize = `${ fontSize }px`;
	}
	return style;
}

/**
 * 1.3 – 1.6: bespoke attributes printed inline, `is-bordered` class.
 */
const v2 = {
	attributes: {
		...legacyAttributes,
		showIcon: {
			type: 'boolean',
			default: true,
		},
		bordered: {
			type: 'boolean',
			default: false,
		},
	},
	supports: {
		html: false,
		anchor: true,
	},
	save( { attributes } ) {
		const { tone, showIcon, bordered } = attributes;
		const blockProps = useBlockProps.save( {
			className: [ `is-tone-${ tone }`, bordered ? 'is-bordered' : '' ]
				.filter( Boolean )
				.join( ' ' ),
			style: legacyStyle( attributes ),
			role: 'note',
		} );
		return (
			<div { ...blockProps }>
				{ showIcon && (
					<span
						className="wp-block-acme-notice-box__icon"
						aria-hidden="true"
					/>
				) }
				<div className="wp-block-acme-notice-box__body">
					<InnerBlocks.Content />
				</div>
			</div>
		);
	},
	migrate: migrateNotice,
};

/**
 * 1.0 – 1.2: `acme-notice acme-notice--{tone}`, always with the 1.0 defaults
 * printed inline (#fff8e1 / #3e2723 / 20px / 16px).
 */
const v1 = {
	attributes: legacyAttributes,
	supports: {
		html: false,
	},
	save( { attributes } ) {
		const { tone, bgColor, textColor, padding, fontSize } = attributes;
		return (
			<div
				className={ `acme-notice acme-notice--${ tone }` }
				style={ {
					backgroundColor: bgColor ?? '#fff8e1',
					color: textColor ?? '#3e2723',
					padding: `${ padding ?? 20 }px`,
					fontSize: `${ fontSize ?? 16 }px`,
				} }
			>
				<InnerBlocks.Content />
			</div>
		);
	},
	migrate( attributes ) {
		return migrateNotice( attributes );
	},
};

export default [ v2, v1 ];
