/**
 * Deprecated versions of the pricing table block.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { escapeHTML } from '@wordpress/escape-html';

/**
 * 1.0–1.2: parallel arrays, US dollars only, one button label for all plans.
 *
 * <div class="wp-block-acme-pricing-table acme-pricing acme-pricing--cols-3">
 *   <div class="acme-pricing__plan acme-pricing__plan--featured">
 *     <h3 class="acme-pricing__name">Pro</h3>
 *     <p class="acme-pricing__price"><span class="acme-pricing__amount">$49</span><span class="acme-pricing__period">/month</span></p>
 *     <ul class="acme-pricing__features"><li>…</li></ul>
 *     <a class="acme-pricing__button" href="…">Choose plan</a>
 *   </div>
 * </div>
 */
const v1 = {
	attributes: {
		planCount: {
			type: 'number',
			default: 3,
		},
		titles: {
			type: 'array',
			default: [],
		},
		prices: {
			type: 'array',
			default: [],
		},
		featureLists: {
			type: 'array',
			default: [],
		},
		featured: {
			type: 'number',
			default: -1,
		},
		buttonLabel: {
			type: 'string',
			default: 'Choose plan',
		},
		buttonUrls: {
			type: 'array',
			default: [],
		},
	},
	supports: {
		html: false,
	},
	migrate( attributes ) {
		const { planCount, titles, prices, featureLists, featured, buttonLabel, buttonUrls } = attributes;
		return {
			columns: planCount,
			currency: 'USD',
			highlighted: featured,
			plans: titles.map( ( title, i ) => ( {
				name: escapeHTML( title || '' ),
				price: String( prices[ i ] ?? '' ),
				period: '/month',
				features: String( featureLists[ i ] || '' )
					.split( '\n' )
					.map( ( line ) => line.trim() )
					.filter( Boolean ),
				buttonText: escapeHTML( buttonLabel || '' ),
				buttonUrl: buttonUrls[ i ] || '',
			} ) ),
		};
	},
	save( { attributes } ) {
		const { planCount, titles, prices, featureLists, featured, buttonLabel, buttonUrls } = attributes;
		const blockProps = useBlockProps.save( {
			className: `acme-pricing acme-pricing--cols-${ planCount }`,
		} );
		return (
			<div { ...blockProps }>
				{ titles.slice( 0, planCount ).map( ( title, i ) => (
					<div
						key={ i }
						className={
							i === featured
								? 'acme-pricing__plan acme-pricing__plan--featured'
								: 'acme-pricing__plan'
						}
					>
						<h3 className="acme-pricing__name">{ title }</h3>
						<p className="acme-pricing__price">
							<span className="acme-pricing__amount">{ '$' + ( prices[ i ] ?? '' ) }</span>
							<span className="acme-pricing__period">/month</span>
						</p>
						<ul className="acme-pricing__features">
							{ String( featureLists[ i ] || '' )
								.split( '\n' )
								.map( ( line ) => line.trim() )
								.filter( Boolean )
								.map( ( line, j ) => (
									<li key={ j }>{ line }</li>
								) ) }
						</ul>
						{ !! buttonUrls[ i ] && (
							<a className="acme-pricing__button" href={ buttonUrls[ i ] }>
								{ buttonLabel }
							</a>
						) }
					</div>
				) ) }
			</div>
		);
	},
};

export default [ v1 ];
