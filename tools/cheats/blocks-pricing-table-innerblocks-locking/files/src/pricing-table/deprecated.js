/**
 * Deprecated versions of the pricing table block.
 *
 * Before 2.0 a table was one block that stored all plans in its attributes.
 * Both old formats are migrated to a table with one pricing plan block per
 * (visible) plan.
 */
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { escapeHTML } from '@wordpress/escape-html';

import { formatPrice } from './currency';
import { planBlockFromLegacy } from './plans';

/**
 * 1.3–1.6: one `plans` attribute with plan objects, per-table currency.
 */
function saveV2( { attributes } ) {
	const { columns, currency, highlighted, plans } = attributes;
	// Plans beyond the column count are kept in the attributes (so reducing the
	// column count is reversible) but not displayed.
	const visiblePlans = plans.slice( 0, columns );

	const blockProps = useBlockProps.save( {
		className: `acme-pricing acme-pricing--cols-${ columns } acme-pricing--currency-${ currency.toLowerCase() }`,
	} );

	return (
		<div { ...blockProps }>
			{ visiblePlans.map( ( plan, index ) => (
				<div
					key={ index }
					className={
						index === highlighted
							? 'acme-pricing__plan is-highlighted'
							: 'acme-pricing__plan'
					}
				>
					<RichText.Content
						tagName="h3"
						className="acme-pricing__name"
						value={ plan.name }
					/>
					<p className="acme-pricing__price">
						<span className="acme-pricing__amount">
							{ formatPrice( plan.price, currency ) }
						</span>
						{ !! plan.period && (
							<RichText.Content
								tagName="span"
								className="acme-pricing__period"
								value={ plan.period }
							/>
						) }
					</p>
					{ plan.features?.length > 0 && (
						<ul className="acme-pricing__features">
							{ plan.features.map( ( feature, i ) => (
								<li key={ i }>{ feature }</li>
							) ) }
						</ul>
					) }
					{ !! plan.buttonUrl && (
						<a className="acme-pricing__button" href={ plan.buttonUrl }>
							{ plan.buttonText }
						</a>
					) }
				</div>
			) ) }
		</div>
	);
}

const v2 = {
	attributes: {
		columns: {
			type: 'number',
			default: 3,
		},
		currency: {
			type: 'string',
			default: 'USD',
		},
		highlighted: {
			type: 'number',
			default: -1,
		},
		plans: {
			type: 'array',
			default: [],
		},
	},
	supports: {
		html: false,
		align: [ 'wide', 'full' ],
		anchor: true,
	},
	migrate( attributes ) {
		const { columns, highlighted, plans, ...rest } = attributes;
		// Plans hidden by the old column count are dropped.
		const innerBlocks = plans
			.slice( 0, columns )
			.map( ( plan, index ) => planBlockFromLegacy( plan, index === highlighted ) );
		return [ rest, innerBlocks ];
	},
	save: saveV2,
};

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
			currency: 'USD',
			columns: planCount,
			highlighted: featured,
			plans: titles.map( ( title, i ) => ( {
				name: escapeHTML( title || '' ),
				price: String( prices[ i ] ?? '' ),
				period: '/month',
				features: String( featureLists[ i ] || '' ).split( '\n' ).filter( Boolean ),
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

export default [ v2, v1 ];
